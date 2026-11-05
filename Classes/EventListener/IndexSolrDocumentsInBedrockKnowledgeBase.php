<?php

declare(strict_types=1);

namespace W3code\W3cAIConnector\EventListener;

use ApacheSolrForTypo3\Solr\Event\Indexing\BeforeDocumentsAreIndexedEvent;
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
    use KnowledgeBaseDocumentTrait;

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
                $documents[] = $this->buildKnowledgeBaseDocument(
                    $document,
                    $siteIdentifier,
                    (string)$document['type'],
                    (int)$document['uid'],
                    $languageId,
                    (string)$document['url']
                );
            }

            $this->knowledgeBaseService->ingestDocuments(array_values(array_filter($documents)));
        } catch (Throwable $e) {
            // the knowledge base must never break the Solr indexing
            $this->logger->error('Bedrock Knowledge Base ingestion failed', ['exception' => $e]);
        }
    }
}
