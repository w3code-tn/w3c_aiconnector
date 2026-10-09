<?php

declare(strict_types=1);

namespace W3code\W3cAIConnector\Service;

use Aws\BedrockAgent\BedrockAgentClient;
use Aws\BedrockAgentRuntime\BedrockAgentRuntimeClient;
use Aws\Exception\AwsException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

/**
 * Class BedrockKnowledgeBaseService
 *
 * Queries an Amazon Bedrock Knowledge Base and manages the documents of its custom data source.
 * AWS credentials are resolved by the AWS SDK default provider chain (IAM role, environment, shared config).
 */
class BedrockKnowledgeBaseService
{
    // maximum number of documents per ingestion or deletion request
    private const BATCH_SIZE = 10;

    private array $config;

    public function __construct(
        ExtensionConfiguration $extensionConfiguration,
        private ?BedrockAgentRuntimeClient $runtimeClient = null,
        private ?BedrockAgentClient $agentClient = null,
    ) {
        $this->config = $extensionConfiguration->get('w3c_aiconnector')['bedrockKnowledgeBase'] ?? [];
    }

    /**
     * answers a question with the knowledge base content, along with the sources the answer is based on
     *
     * @param string $question
     * @param array $configuration overrides of the AWS knowledgeBaseConfiguration, e.g. a retrieval filter or a prompt template
     * @param string|null $sessionId the sessionId of a previous answer, to continue the conversation
     *
     * @return array{answer: string, sessionId: string, citations: list<array{text: string, sources: list<array{content: string, location: array, metadata: array}>}>}
     *
     * @throws AwsException
     */
    public function retrieveAndGenerate(string $question, array $configuration = [], ?string $sessionId = null): array
    {
        $result = $this->getRuntimeClient()->retrieveAndGenerate(array_filter([
            'input' => ['text' => $question],
            'retrieveAndGenerateConfiguration' => [
                'type' => 'KNOWLEDGE_BASE',
                'knowledgeBaseConfiguration' => array_replace_recursive([
                    'knowledgeBaseId' => $this->config['knowledgeBaseId'] ?? '',
                    'modelArn' => $this->config['modelArn'] ?? '',
                    'retrievalConfiguration' => [
                        'vectorSearchConfiguration' => ['numberOfResults' => (int)($this->config['numberOfResults'] ?? 5)],
                    ],
                ], $configuration),
            ],
            'sessionId' => $sessionId,
        ]));

        return [
            'answer' => $result['output']['text'],
            'sessionId' => $result['sessionId'],
            'citations' => array_map(static fn(array $citation): array => [
                'text' => $citation['generatedResponsePart']['textResponsePart']['text'] ?? '',
                'sources' => array_map(static fn(array $reference): array => [
                    'content' => $reference['content']['text'] ?? '',
                    'location' => $reference['location'] ?? [],
                    'metadata' => $reference['metadata'] ?? [],
                ], $citation['retrievedReferences'] ?? []),
            ], $result['citations'] ?? []),
        ];
    }

    /**
     * searches the knowledge base content most relevant to a query, without generating an answer.
     * Passages scoring below the minimum score are left out: a vector search always returns its nearest passages,
     * even for questions the knowledge base knows nothing about.
     *
     * @param array $configuration overrides of the AWS retrievalConfiguration, e.g. a filter
     *
     * @return list<array{score: float, content: string, location: array, metadata: array}>
     *
     * @throws AwsException
     */
    public function retrieve(string $query, array $configuration = []): array
    {
        $result = $this->getRuntimeClient()->retrieve([
            'knowledgeBaseId' => $this->config['knowledgeBaseId'] ?? '',
            'retrievalQuery' => ['text' => $query],
            'retrievalConfiguration' => array_replace_recursive([
                'vectorSearchConfiguration' => ['numberOfResults' => (int)($this->config['numberOfResults'] ?? 5)],
            ], $configuration),
        ]);

        $passages = array_map(static fn(array $retrievalResult): array => [
            'score' => (float)($retrievalResult['score'] ?? 0),
            'content' => $retrievalResult['content']['text'] ?? '',
            'location' => $retrievalResult['location'] ?? [],
            'metadata' => $retrievalResult['metadata'] ?? [],
        ], $result['retrievalResults'] ?? []);

        return array_values(array_filter($passages, fn(array $passage): bool => $passage['score'] >= $this->getMinimumScore()));
    }

    /**
     * minimum relevance score of the retrieved passages, from 0 (everything) to 1
     */
    public function getMinimumScore(): float
    {
        return (float)($this->config['minimumScore'] ?? 0.45);
    }

    /**
     * adds the documents to the custom data source, replacing the ones with the same id
     *
     * @param list<array{id: string, text: string, metadata?: array<string, string|int|float>}> $documents
     *
     * @throws AwsException
     */
    public function ingestDocuments(array $documents): void
    {
        foreach (array_chunk($documents, self::BATCH_SIZE) as $batch) {
            $this->getAgentClient()->ingestKnowledgeBaseDocuments($this->getDataSource() + [
                'documents' => array_map($this->buildDocument(...), $batch),
            ]);
        }
    }

    /**
     * removes the documents from the custom data source
     *
     * @param list<string> $ids
     *
     * @throws AwsException
     */
    public function deleteDocuments(array $ids): void
    {
        foreach (array_chunk($ids, self::BATCH_SIZE) as $batch) {
            $this->getAgentClient()->deleteKnowledgeBaseDocuments($this->getDataSource() + [
                'documentIdentifiers' => array_map(static fn(string $id): array => [
                    'dataSourceType' => 'CUSTOM',
                    'custom' => ['id' => $id],
                ], $batch),
            ]);
        }
    }

    /**
     * whether a custom data source is configured to be fed by the TYPO3 content
     */
    public function isSyncEnabled(): bool
    {
        return !empty($this->config['knowledgeBaseId']) && !empty($this->config['dataSourceId']);
    }

    /**
     * whether a model is configured to generate the answers
     */
    public function canGenerateAnswers(): bool
    {
        return !empty($this->config['modelArn']);
    }

    /**
     * returns the document id of a TYPO3 record: one document per site, record and language
     */
    public static function getRecordDocumentId(string $siteIdentifier, string $table, int $uid, int $languageId): string
    {
        return implode('/', [$siteIdentifier, $table, $uid, $languageId]);
    }

    private function buildDocument(array $document): array
    {
        $attributes = [];
        foreach (array_filter($document['metadata'] ?? [], static fn($value): bool => $value !== '') as $key => $value) {
            $attributes[] = [
                'key' => $key,
                'value' => is_string($value)
                    ? ['type' => 'STRING', 'stringValue' => $value]
                    : ['type' => 'NUMBER', 'numberValue' => $value],
            ];
        }

        return array_filter([
            'content' => [
                'dataSourceType' => 'CUSTOM',
                'custom' => [
                    'customDocumentIdentifier' => ['id' => $document['id']],
                    'sourceType' => 'IN_LINE',
                    'inlineContent' => [
                        'type' => 'TEXT',
                        'textContent' => ['data' => $document['text']],
                    ],
                ],
            ],
            'metadata' => $attributes ? ['type' => 'IN_LINE_ATTRIBUTE', 'inlineAttributes' => $attributes] : null,
        ]);
    }

    private function getDataSource(): array
    {
        return [
            'knowledgeBaseId' => $this->config['knowledgeBaseId'] ?? '',
            'dataSourceId' => $this->config['dataSourceId'] ?? '',
        ];
    }

    private function getRuntimeClient(): BedrockAgentRuntimeClient
    {
        return $this->runtimeClient ??= new BedrockAgentRuntimeClient($this->getClientConfiguration());
    }

    private function getAgentClient(): BedrockAgentClient
    {
        return $this->agentClient ??= new BedrockAgentClient($this->getClientConfiguration());
    }

    private function getClientConfiguration(): array
    {
        // no credentials: the SDK resolves them with its default provider chain
        return [
            'region' => $this->config['region'] ?? 'us-east-1',
            'version' => 'latest',
        ];
    }
}
