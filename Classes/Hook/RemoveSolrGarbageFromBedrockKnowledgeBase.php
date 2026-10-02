<?php

declare(strict_types=1);

namespace W3code\W3cAIConnector\Hook;

use ApacheSolrForTypo3\Solr\GarbageCollectorPostProcessor;
use Throwable;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use W3code\W3cAIConnector\Service\BedrockKnowledgeBaseService;

/**
 * Class RemoveSolrGarbageFromBedrockKnowledgeBase
 *
 * Removes from the Bedrock Knowledge Base the records EXT:solr removes from its index (deleted, hidden...).
 * EXT:solr provides no PSR-14 event for it, hence the hook.
 */
final class RemoveSolrGarbageFromBedrockKnowledgeBase implements GarbageCollectorPostProcessor
{
    public function postProcessGarbageCollector(string $table, int $uid): void
    {
        // a content element change makes EXT:solr re-index its page, which replaces the page document
        if ($table === 'tt_content') {
            return;
        }

        $knowledgeBaseService = GeneralUtility::makeInstance(BedrockKnowledgeBaseService::class);
        if (!$knowledgeBaseService->isSyncEnabled()) {
            return;
        }

        // as in EXT:solr, a translation is indexed under its default language record and only removes its own language
        $languageField = $GLOBALS['TCA'][$table]['ctrl']['languageField'] ?? '';
        $parentField = $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'] ?? '';
        $record = $languageField && $parentField
            ? BackendUtility::getRecord($table, $uid, $languageField . ',' . $parentField, '', false)
            : null;
        $parentUid = (int)($record[$parentField] ?? 0);

        $ids = [];
        foreach (GeneralUtility::makeInstance(SiteFinder::class)->getAllSites() as $site) {
            $languageIds = $parentUid > 0 ? [(int)$record[$languageField]] : array_keys($site->getAllLanguages());
            foreach ($languageIds as $languageId) {
                $ids[] = BedrockKnowledgeBaseService::getRecordDocumentId($site->getIdentifier(), $table, $parentUid ?: $uid, $languageId);
            }
        }

        try {
            $knowledgeBaseService->deleteDocuments($ids);
        } catch (Throwable $e) {
            GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__)
                ->error('Bedrock Knowledge Base deletion failed', ['exception' => $e]);
        }
    }
}
