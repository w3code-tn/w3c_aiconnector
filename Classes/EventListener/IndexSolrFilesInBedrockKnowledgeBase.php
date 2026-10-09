<?php

declare(strict_types=1);

namespace W3code\W3cAIConnector\EventListener;

use ApacheSolrForTypo3\Solrfal\Event\AfterFileDocumentHasBeenIndexedEvent;
use Psr\Log\LoggerInterface;
use Throwable;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Site\Entity\Site;
use W3code\W3cAIConnector\Service\BedrockKnowledgeBaseService;

/**
 * Class IndexSolrFilesInBedrockKnowledgeBase
 *
 * Feeds the Bedrock Knowledge Base with the files EXT:solrfal indexes, with the text extracted for Solr.
 * EXT:solrfal indexes a file in every site language, it is sent once to the knowledge base: in its own language.
 */
final class IndexSolrFilesInBedrockKnowledgeBase
{
    use KnowledgeBaseDocumentTrait;

    public function __construct(
        private readonly BedrockKnowledgeBaseService $knowledgeBaseService,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(AfterFileDocumentHasBeenIndexedEvent $event): void
    {
        if (!$this->knowledgeBaseService->isSyncEnabled()) {
            return;
        }

        try {
            $document = $event->getDocument();
            $context = $event->getItem()->getContext();
            $site = $context->getSite()->getTypo3SiteObject();
            if ((int)$context->getLanguage() !== $this->getFileLanguageId($event->getItem()->getFile(), $site)) {
                return;
            }

            $knowledgeBaseDocument = $this->buildKnowledgeBaseDocument(
                $document,
                $site->getIdentifier(),
                'sys_file',
                $event->getItem()->getFileUid(),
                (int)$context->getLanguage(),
                $this->getAbsoluteUrl($site, (string)$document['url'])
            );
            if ($knowledgeBaseDocument !== null) {
                $this->knowledgeBaseService->ingestDocuments([$knowledgeBaseDocument]);
            }
        } catch (Throwable $e) {
            // the knowledge base must never break the Solr indexing
            $this->logger->error('Bedrock Knowledge Base ingestion of a file failed', ['exception' => $e]);
        }
    }

    /**
     * returns the site language of the file metadata language (ISO code), the default language otherwise
     */
    private function getFileLanguageId(File $file, Site $site): int
    {
        $languageCode = strtolower(trim((string)($file->getMetaData()['language'] ?? '')));
        foreach ($site->getAllLanguages() as $siteLanguage) {
            if ($languageCode !== '' && $siteLanguage->getLocale()->getLanguageCode() === $languageCode) {
                return $siteLanguage->getLanguageId();
            }
        }
        return $site->getDefaultLanguage()->getLanguageId();
    }

    /**
     * file urls are relative to the site, e.g. "fileadmin/documents/guide.pdf"
     */
    private function getAbsoluteUrl(Site $site, string $url): string
    {
        if ($url === '' || preg_match('#^https?://#', $url)) {
            return $url;
        }
        return rtrim((string)$site->getBase(), '/') . '/' . ltrim($url, '/');
    }
}
