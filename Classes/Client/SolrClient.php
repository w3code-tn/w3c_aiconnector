<?php

declare(strict_types=1);

namespace W3code\W3cAIConnector\Client;

use GuzzleHttp\Client;
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

        if (!empty($options['endPoint'])) {
            $url = $options['endPoint'] . $solrCore . '/select?q=' . urlencode($keywords);

            if (!empty($options['defType'])) {
                $url .= '&defType=' . urlencode($options['defType']);
            }

            if (!empty($options['qf'])) {
                $url .= '&qf=' . urlencode($options['qf']);
            }

            if (!empty($options['rows'])) {
                $url .= '&rows=' . (int)$options['rows'];
            }

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
                if (!empty($options['hl_bs_type'])) {
                    $url .= '&hl.bs.type=' . urlencode($options['hl_bs_type']);
                }
            }

            try {
                return $this->client->get($url);
            } catch (GuzzleException $e) {
                $this->logger->error('Solr Error', ['error' => $e->getMessage()]);
                throw $e;
            }
        }

        throw new \RuntimeException('Solr endpoint is not configured.');
    }
}
