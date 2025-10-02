<?php
declare(strict_types=1);

namespace W3code\W3cAiconnector\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class SolrService
{
    private const SOLR_ENDPOINT = 'http://solr:8983/solr/';

    private LoggerInterface $logger;
    private array $extConf;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->extConf = GeneralUtility::makeInstance(ExtensionConfiguration::class)
            ->get('w3c_aiconnector');
    }

    public function search(string $keywords, SiteLanguage $siteLanguage, array $filters = []): array
    {
        $solrCore = 'core_' . strtolower($siteLanguage->getHreflang());
        $solrUrl = self::SOLR_ENDPOINT . $solrCore . '/select?q=' . urlencode($keywords);

        if (!empty($filters)) {
            foreach ($filters as $filter) {
                $solrUrl .= '&fq=' . urlencode(stripslashes($filter));
            }
            $this->logger->info('Solr Filters', ['filters' => $filters]);
        }

        if (!empty($this->extConf['hl_enabled'])) {
            $solrUrl .= '&hl=true';
            if (!empty($this->extConf['hl_fl'])) {
                $solrUrl .= '&hl.fl=' . urlencode($this->extConf['hl_fl']);
            }
            if (!empty($this->extConf['hl_snippets'])) {
                $solrUrl .= '&hl.snippets=' . (int)$this->extConf['hl_snippets'];
            }
            if (!empty($this->extConf['hl_fragsize'])) {
                $solrUrl .= '&hl.fragsize=' . (int)$this->extConf['hl_fragsize'];
            }
            if (!empty($this->extConf['hl_mergeContiguous'])) {
                $solrUrl .= '&hl.mergeContiguous=true';
            }
        }

        $client = new Client();
        $results = [];
        try {
            $response = $client->get($solrUrl);
            $solrData = json_decode((string)$response->getBody(), true);

            $docs = $solrData['response']['docs'] ?? [];
            if (!empty($this->extConf['hl_enabled']) && isset($solrData['highlighting'])) {
                $highlighting = $solrData['highlighting'];
                $highlightField = $this->extConf['hl_fl'] ?? 'contenu';

                foreach ($docs as &$doc) {
                    $id = $doc['id'];
                    if (isset($highlighting[$id]) && !empty($highlighting[$id][$highlightField])) {
                        $doc['contenu'] = implode(' ... ', $highlighting[$id][$highlightField]);
                    } else {
                        unset($doc['contenu']);
                    }
                }
            }
            $results = $docs;

        } catch (GuzzleException $e) {
            $this->logger->error('Solr Error', ['error' => $e->getMessage()]);
        }

        return $results;
    }
}
