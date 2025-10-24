<?php

declare(strict_types=1);

namespace W3code\W3cAIConnector\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use W3code\W3cAIConnector\Utility\LogUtility;

/**
 * Class DeepLClient
 */
class DeepLClient implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const API_ENDPOINT = 'https://api.deepl.com/v2/translate';
    private const FREE_API_ENDPOINT = 'https://api-free.deepl.com/v2/translate';

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
     * @return string|ResponseInterface
     * @throws GuzzleException
     */
    public function generateResponse(string $prompt, array $options = []): string|ResponseInterface
    {
        $url = self::API_ENDPOINT;
        if (($options['apiVersion'] ?? 'free') === 'free') {
            $url = self::FREE_API_ENDPOINT;
        }

        $formParams = [
            'auth_key' => $options['apiKey'],
            'text' => $prompt,
            'target_lang' => $options['target_lang'],
        ];

        if (!empty($options['source_lang'])) {
            $formParams['source_lang'] = $options['source_lang'];
        }
        if (!empty($options['split_sentences'])) {
            $formParams['split_sentences'] = $options['split_sentences'];
        }
        if (!empty($options['preserve_formatting'])) {
            $formParams['preserve_formatting'] = 1;
        }
        if (!empty($options['formality'])) {
            $formParams['formality'] = $options['formality'];
        }
        if (!empty($options['glossary_id'])) {
            $formParams['glossary_id'] = $options['glossary_id'];
        }
        if (!empty($options['tag_handling'])) {
            $formParams['tag_handling'] = $options['tag_handling'];
        }
        if (!empty($options['outline_detection'])) {
            $formParams['outline_detection'] = 1;
        }
        if (!empty($options['non_splitting_tags'])) {
            $formParams['non_splitting_tags'] = $options['non_splitting_tags'];
        }

        try {
            return $this->client->post($url, [
                'form_params' => $formParams,
            ]);
        } catch (GuzzleException $e) {
            LogUtility::logException($options);
            throw $e;
        }
    }
}
