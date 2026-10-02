<?php

declare(strict_types=1);

namespace W3code\W3cAIConnector\Tests\Functional\Service;

use Aws\BedrockAgent\BedrockAgentClient;
use Aws\BedrockAgentRuntime\BedrockAgentRuntimeClient;
use Aws\Command;
use Aws\CommandInterface;
use Aws\Exception\AwsException;
use Aws\MockHandler;
use Aws\Result;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use W3code\W3cAIConnector\Service\BedrockKnowledgeBaseService;

class BedrockKnowledgeBaseServiceTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['w3code/w3c-aiconnector'];

    private MockHandler $mockHandler;
    private BedrockKnowledgeBaseService $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willReturn([
            'bedrockKnowledgeBase' => [
                'region' => 'eu-west-1',
                'knowledgeBaseId' => 'KB12345678',
                'dataSourceId' => 'DS12345678',
                'modelArn' => 'arn:aws:bedrock:eu-west-1::foundation-model/test-model',
                'numberOfResults' => 3,
            ],
        ]);

        $this->mockHandler = new MockHandler();
        $clientConfiguration = [
            'region' => 'eu-west-1',
            'version' => 'latest',
            'credentials' => false,
            'handler' => $this->mockHandler,
        ];

        $this->subject = new BedrockKnowledgeBaseService(
            $extensionConfiguration,
            new BedrockAgentRuntimeClient($clientConfiguration),
            new BedrockAgentClient($clientConfiguration)
        );
    }

    #[Test]
    public function testServiceIsAutowired(): void
    {
        $service = $this->get(BedrockKnowledgeBaseService::class);

        self::assertInstanceOf(BedrockKnowledgeBaseService::class, $service);
        self::assertFalse($service->isSyncEnabled());
    }

    #[Test]
    public function testRetrieveAndGenerateReturnsAnswerWithCitations(): void
    {
        $this->mockHandler->append(function (CommandInterface $command): Result {
            self::assertSame('RetrieveAndGenerate', $command->getName());
            self::assertSame(['text' => 'What does W3code do?'], $command['input']);
            self::assertSame([
                'type' => 'KNOWLEDGE_BASE',
                'knowledgeBaseConfiguration' => [
                    'knowledgeBaseId' => 'KB12345678',
                    'modelArn' => 'arn:aws:bedrock:eu-west-1::foundation-model/test-model',
                    'retrievalConfiguration' => [
                        'vectorSearchConfiguration' => ['numberOfResults' => 3],
                    ],
                ],
            ], $command['retrieveAndGenerateConfiguration']);
            self::assertArrayNotHasKey('sessionId', $command->toArray());

            return new Result([
                'output' => ['text' => 'W3code builds TYPO3 websites.'],
                'sessionId' => 'session-1',
                'citations' => [
                    [
                        'generatedResponsePart' => [
                            'textResponsePart' => ['text' => 'W3code builds TYPO3 websites.', 'span' => ['start' => 0, 'end' => 28]],
                        ],
                        'retrievedReferences' => [
                            [
                                'content' => ['text' => 'We build websites with TYPO3.', 'type' => 'TEXT'],
                                'location' => ['type' => 'CUSTOM', 'customDocumentLocation' => ['id' => 'main/pages/1/0']],
                                'metadata' => ['title' => 'Home', 'url' => 'https://w3code.tn/'],
                            ],
                        ],
                    ],
                ],
            ]);
        });

        $result = $this->subject->retrieveAndGenerate('What does W3code do?');

        self::assertSame([
            'answer' => 'W3code builds TYPO3 websites.',
            'sessionId' => 'session-1',
            'citations' => [
                [
                    'text' => 'W3code builds TYPO3 websites.',
                    'sources' => [
                        [
                            'content' => 'We build websites with TYPO3.',
                            'location' => ['type' => 'CUSTOM', 'customDocumentLocation' => ['id' => 'main/pages/1/0']],
                            'metadata' => ['title' => 'Home', 'url' => 'https://w3code.tn/'],
                        ],
                    ],
                ],
            ],
        ], $result);
    }

    #[Test]
    public function testRetrieveAndGenerateWithoutCitations(): void
    {
        $this->mockHandler->append(new Result([
            'output' => ['text' => 'Sorry, I am unable to assist you with this request.'],
            'sessionId' => 'session-1',
        ]));

        $result = $this->subject->retrieveAndGenerate('Unknown question');

        self::assertSame('Sorry, I am unable to assist you with this request.', $result['answer']);
        self::assertSame([], $result['citations']);
    }

    #[Test]
    public function testRetrieveAndGenerateAppliesConfigurationAndSession(): void
    {
        $filter = ['equals' => ['key' => 'language', 'value' => 1]];
        $this->mockHandler->append(function (CommandInterface $command) use ($filter): Result {
            self::assertSame('session-1', $command['sessionId']);
            self::assertSame(
                ['numberOfResults' => 3, 'filter' => $filter],
                $command['retrieveAndGenerateConfiguration']['knowledgeBaseConfiguration']['retrievalConfiguration']['vectorSearchConfiguration']
            );

            return new Result(['output' => ['text' => 'answer'], 'sessionId' => 'session-1']);
        });

        $this->subject->retrieveAndGenerate(
            'question',
            ['retrievalConfiguration' => ['vectorSearchConfiguration' => ['filter' => $filter]]],
            'session-1'
        );
    }

    #[Test]
    public function testRetrieveAndGenerateThrowsAwsErrors(): void
    {
        $this->mockHandler->append(new AwsException('User is not authorized', new Command('RetrieveAndGenerate'), [
            'code' => 'AccessDeniedException',
        ]));

        $this->expectException(AwsException::class);
        $this->expectExceptionMessage('User is not authorized');

        $this->subject->retrieveAndGenerate('question');
    }

    #[Test]
    public function testIngestDocumentsSendsBatchesOfInlineDocuments(): void
    {
        $documents = [];
        for ($uid = 1; $uid <= 11; $uid++) {
            $documents[] = [
                'id' => 'main/pages/' . $uid . '/0',
                'text' => 'Page ' . $uid,
                'metadata' => ['uid' => $uid, 'title' => 'Page ' . $uid, 'url' => ''],
            ];
        }
        $requests = [];
        $collect = function (CommandInterface $command) use (&$requests): Result {
            $requests[] = $command->toArray();
            return new Result([]);
        };
        $this->mockHandler->append($collect, $collect);

        $this->subject->ingestDocuments($documents);

        self::assertCount(2, $requests);
        self::assertSame('KB12345678', $requests[0]['knowledgeBaseId']);
        self::assertSame('DS12345678', $requests[0]['dataSourceId']);
        self::assertCount(10, $requests[0]['documents']);
        self::assertSame([
            'content' => [
                'dataSourceType' => 'CUSTOM',
                'custom' => [
                    'customDocumentIdentifier' => ['id' => 'main/pages/11/0'],
                    'sourceType' => 'IN_LINE',
                    'inlineContent' => [
                        'type' => 'TEXT',
                        'textContent' => ['data' => 'Page 11'],
                    ],
                ],
            ],
            'metadata' => [
                'type' => 'IN_LINE_ATTRIBUTE',
                'inlineAttributes' => [
                    ['key' => 'uid', 'value' => ['type' => 'NUMBER', 'numberValue' => 11]],
                    ['key' => 'title', 'value' => ['type' => 'STRING', 'stringValue' => 'Page 11']],
                ],
            ],
        ], $requests[1]['documents'][0]);
    }

    #[Test]
    public function testDeleteDocumentsSendsCustomIdentifiers(): void
    {
        $this->mockHandler->append(function (CommandInterface $command): Result {
            self::assertSame('DeleteKnowledgeBaseDocuments', $command->getName());
            self::assertSame([
                ['dataSourceType' => 'CUSTOM', 'custom' => ['id' => 'main/pages/1/0']],
                ['dataSourceType' => 'CUSTOM', 'custom' => ['id' => 'main/pages/1/1']],
            ], $command['documentIdentifiers']);

            return new Result([]);
        });

        $this->subject->deleteDocuments(['main/pages/1/0', 'main/pages/1/1']);

        self::assertCount(0, $this->mockHandler);
    }
}
