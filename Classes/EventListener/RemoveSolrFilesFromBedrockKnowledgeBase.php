<?php

declare(strict_types=1);

namespace W3code\W3cAIConnector\EventListener;

use ApacheSolrForTypo3\Solrfal\Event\BeforeItemRemovedEvent;
use ApacheSolrForTypo3\Solrfal\Event\BeforeMultipleItemsRemovedEvent;
use ApacheSolrForTypo3\Solrfal\Queue\Item;
use ApacheSolrForTypo3\Solrfal\Queue\ItemRepository;
use Psr\Log\LoggerInterface;
use Throwable;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use W3code\W3cAIConnector\Service\BedrockKnowledgeBaseService;

/**
 * Class RemoveSolrFilesFromBedrockKnowledgeBase
 *
 * Removes from the Bedrock Knowledge Base the files EXT:solrfal removes from its queue (deleted, moved out...).
 */
final class RemoveSolrFilesFromBedrockKnowledgeBase
{
    public function __construct(
        private readonly BedrockKnowledgeBaseService $knowledgeBaseService,
        private readonly LoggerInterface $logger,
    ) {}

    public function removeItem(BeforeItemRemovedEvent $event): void
    {
        $this->remove([$event->getItem()]);
    }

    public function removeItems(BeforeMultipleItemsRemovedEvent $event): void
    {
        if (!$this->knowledgeBaseService->isSyncEnabled()) {
            return;
        }

        // the items are still in the queue
        $itemRepository = GeneralUtility::makeInstance(ItemRepository::class);
        $this->remove(array_filter(array_map(
            static fn(int|string $uid): ?Item => $itemRepository->findByUid((int)$uid),
            $event->getItemUids()
        )));
    }

    /**
     * @param Item[] $items
     */
    private function remove(array $items): void
    {
        if ($items === [] || !$this->knowledgeBaseService->isSyncEnabled()) {
            return;
        }

        try {
            // a file is in the knowledge base in one of the site languages, whose metadata may already be gone
            $ids = [];
            foreach ($items as $item) {
                $site = $item->getContext()->getSite()->getTypo3SiteObject();
                foreach ($site->getAllLanguages() as $siteLanguage) {
                    $ids[] = BedrockKnowledgeBaseService::getRecordDocumentId($site->getIdentifier(), 'sys_file', $item->getFileUid(), $siteLanguage->getLanguageId());
                }
            }
            $this->knowledgeBaseService->deleteDocuments(array_values(array_unique($ids)));
        } catch (Throwable $e) {
            $this->logger->error('Bedrock Knowledge Base deletion of files failed', ['exception' => $e]);
        }
    }
}
