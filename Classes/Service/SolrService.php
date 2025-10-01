<?php
declare(strict_types=1);

namespace W3code\W3cAiconnector\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

class SolrService
{
    private const SOLR_ENDPOINT = 'http://solr:8983/solr/';

    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
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

        $client = new Client();
        $results = [];
        try {
            $response = $client->get($solrUrl);
            $solrData = json_decode((string)$response->getBody(), true);
            $results = $solrData['response']['docs'] ?? [];
        } catch (GuzzleException $e) {
            $this->logger->error('Solr Error', ['error' => $e->getMessage()]);
        }

        return $results;
    }
}
