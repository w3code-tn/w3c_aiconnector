<?php

declare(strict_types=1);

namespace W3code\W3cAIConnector\Client;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * Class SolrClient
 */
class SolrClient implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const SOLR_ENDPOINT = 'http://solr:8983/solr/';

    protected ?Client $client = null;

    public function __construct()
    {
        $this->client = new Client();
    }

    /**
     * @param string $prompt
     * @param array $options
     * @param bool $stream
     *
     * @return ResponseInterface
     *
     * @throws GuzzleException
     */
    public function generateResponse(string $solrCore, array $options, string $keywords, array $filters): ResponseInterface
    {

        $url = self::SOLR_ENDPOINT . $solrCore . '/select?q=' . urlencode($keywords);

        if (!empty($filters)) {
            foreach ($filters as $filter) {
                $url .= '&fq=' . urlencode(stripslashes($filter));
            }
            $this->logger->info('Solr Filters', ['filters' => $filters]);
        }

        if (!empty($options['hl_enabled'])) {
            $url .= '&hl=true';
            if (!empty($options['hl_fl'])) {
                $url .= '&hl.fl=' . urlencode($options['hl_fl']);
            }
            if (!empty($options['hl_snippets'])) {
                $url .= '&hl.snippets=' . (int)$options['hl_snippets'];
            }
            if (!empty($options['hl_fragsize'])) {
                $url .= '&hl.fragsize=' . (int)$options['hl_fragsize'];
            }
            if (!empty($options['hl_mergeContiguous'])) {
                $url .= '&hl.mergeContiguous=true';
            }
        }

        try {
            return $this->client->get($url);
        } catch (ClientException $e) {
            $this->logger->error('Solr Error', ['error' => $e->getMessage()]);
            throw new Exception($e->getMessage(), 1509741909, $e);
        }
    }
}
