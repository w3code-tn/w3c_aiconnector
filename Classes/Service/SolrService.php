<?php

declare(strict_types=1);

namespace W3code\W3cAIConnector\Service;

use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use W3code\W3cAIConnector\Client\SolrClient;
use W3code\W3cAIConnector\Utility\ConfigurationUtility;

/**
 * Class SolrService
 */
class SolrService implements ServiceInterface
{
    use LoggerAwareTrait;

    private array $extConfig;
    protected ?SolrClient $client = null;

    /**
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     */
    public function __construct()
    {
        $this->extConfig = ConfigurationUtility::getExtensionConfiguration('w3c_aiconnector')['solr'] ?? [];
        $this->client = new SolrClient();
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

        $response = $this->client->generateResponse($solrCore, $this->extConfig, $keywords, $filters);
        $data = json_decode((string)$response->getBody(), true);

        $results = [];

        $docs = $data['response']['docs'] ?? [];
        if (!empty($this->extConfig['hl_enabled']) && isset($data['highlighting'])) {
            $highlighting = $data['highlighting'];
            $highlightField = $this->extConfig['hl_fl'] ?? 'contenu';

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
        return $results;
    }
}
