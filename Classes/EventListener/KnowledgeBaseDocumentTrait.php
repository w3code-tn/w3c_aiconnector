<?php

declare(strict_types=1);

namespace W3code\W3cAIConnector\EventListener;

use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;
use W3code\W3cAIConnector\Service\BedrockKnowledgeBaseService;

/**
 * Builds the Bedrock Knowledge Base document of a Solr document
 */
trait KnowledgeBaseDocumentTrait
{
    /**
     * Returns null for the documents without content or restricted to frontend user groups
     *
     * @return array{id: string, text: string, metadata: array<string, string|int>}|null
     */
    private function buildKnowledgeBaseDocument(Document $document, string $siteIdentifier, string $type, int $uid, int $languageId, string $url): ?array
    {
        if (empty($document['content']) || !$this->isPublic($document)) {
            return null;
        }

        return [
            'id' => BedrockKnowledgeBaseService::getRecordDocumentId($siteIdentifier, $type, $uid, $languageId),
            'text' => trim($document['title'] . "\n\n" . $document['content']),
            'metadata' => [
                'type' => $type,
                'uid' => $uid,
                'site' => $siteIdentifier,
                'language' => $languageId,
                'title' => (string)$document['title'],
                'url' => $url,
            ],
        ];
    }

    /**
     * the knowledge base has no access control: documents restricted to frontend user groups are kept out of it.
     * EXT:solr access rootline e.g. "c:0" or "r:0" (public), "12:1/c:0" (page 12 restricted to group 1)
     */
    private function isPublic(Document $document): bool
    {
        foreach ((array)($document['access'] ?? 'r:0') as $accessRootline) {
            foreach (explode('/', (string)$accessRootline) as $element) {
                if (!str_ends_with($element, ':0')) {
                    return false;
                }
            }
        }
        return true;
    }
}
