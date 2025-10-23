<?php
declare(strict_types=1);

namespace W3code\W3cAIConnector\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use W3code\W3cAIConnector\Utility\ConfigurationUtility;

/**
 * Class SolrService
 */
class SolrService implements ServiceInterface
{
    use LoggerAwareTrait;

    private const SOLR_ENDPOINT = 'http://solr:8983/solr/';
    private array $config;

    /**
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     */
    public function __construct()
    {
        $this->config = ConfigurationUtility::getExtensionConfiguration();
    }

    /**
     * @param string $keywords
     * @param SiteLanguage $siteLanguage
     * @param array $filters
     * @return array
     */
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

        if (!empty($this->config['hl_enabled'])) {
            $solrUrl .= '&hl=true';
            if (!empty($this->config['hl_fl'])) {
                $solrUrl .= '&hl.fl=' . urlencode($this->config['hl_fl']);
            }
            if (!empty($this->config['hl_snippets'])) {
                $solrUrl .= '&hl.snippets=' . (int)$this->config['hl_snippets'];
            }
            if (!empty($this->config['hl_fragsize'])) {
                $solrUrl .= '&hl.fragsize=' . (int)$this->config['hl_fragsize'];
            }
            if (!empty($this->config['hl_mergeContiguous'])) {
                $solrUrl .= '&hl.mergeContiguous=true';
            }
        }

        $client = new Client();
        $results = [];
        try {
            $response = $client->get($solrUrl);
            $solrData = json_decode((string)$response->getBody(), true);

            $docs = $solrData['response']['docs'] ?? [];
            if (!empty($this->config['hl_enabled']) && isset($solrData['highlighting'])) {
                $highlighting = $solrData['highlighting'];
                $highlightField = $this->config['hl_fl'] ?? 'contenu';

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
