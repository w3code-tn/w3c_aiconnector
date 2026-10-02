<?php

declare(strict_types=1);

namespace W3code\W3cAIConnector\EventListener;

use ApacheSolrForTypo3\Solr\Event\Indexing\BeforeDocumentsAreIndexedEvent;
use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;
use Psr\Log\LoggerInterface;
use Throwable;
use W3code\W3cAIConnector\Service\BedrockKnowledgeBaseService;

/**
 * Class IndexSolrDocumentsInBedrockKnowledgeBase
 *
 * Feeds the Bedrock Knowledge Base with the documents EXT:solr indexes.
 */
final class IndexSolrDocumentsInBedrockKnowledgeBase
{
    public function __construct(
        private readonly BedrockKnowledgeBaseService $knowledgeBaseService,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(BeforeDocumentsAreIndexedEvent $event): void
    {
        if (!$this->knowledgeBaseService->isSyncEnabled()) {
            return;
        }

        try {
            $siteIdentifier = $event->getSite()->getIdentifier();
            $languageId = $event->getSiteLanguage()->getLanguageId();

            $documents = [];
            foreach ($event->getDocuments() as $document) {
                if (empty($document['content']) || !$this->isPublic($document)) {
                    continue;
                }
                $documents[] = [
                    'id' => BedrockKnowledgeBaseService::getRecordDocumentId($siteIdentifier, $document['type'], (int)$document['uid'], $languageId),
                    'text' => trim($document['title'] . "\n\n" . $document['content']),
                    'metadata' => [
                        'type' => $document['type'],
                        'uid' => (int)$document['uid'],
                        'site' => $siteIdentifier,
                        'language' => $languageId,
                        'title' => (string)$document['title'],
                        'url' => (string)$document['url'],
                    ],
                ];
            }

            $this->knowledgeBaseService->ingestDocuments($documents);
        } catch (Throwable $e) {
            // the knowledge base must never break the Solr indexing
            $this->logger->error('Bedrock Knowledge Base ingestion failed', ['exception' => $e]);
        }
    }

    /**
     * the knowledge base has no access control: documents restricted to frontend user groups are kept out of it.
     * EXT:solr access rootline e.g. "c:0" or "r:0" (public), "12:1/c:0" (page 12 restricted to group 1)
     */
    private function isPublic(Document $document): bool
    {
        foreach (explode('/', (string)($document['access'] ?? 'r:0')) as $element) {
            if (!str_ends_with($element, ':0')) {
                return false;
            }
        }
        return true;
    }
}
