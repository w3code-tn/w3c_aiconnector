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
 * Class ClaudeClient
 */
class ClaudeClient implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const API_ENDPOINT = 'https://api.anthropic.com/v1/messages';

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
     * @throws GuzzleException
     */
    public function generateResponse(string $prompt, array $options = [], bool $stream = false): ResponseInterface
    {
        $url = self::API_ENDPOINT;

        $jsonBody = [
            'model' => $options['model'],
            'max_tokens' => $options['maxTokens'],
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
        ];

        if (!empty($options['system'])) {
            $jsonBody['system'] = $options['system'];
        }
        if (!empty($options['stopSequences'])) {
            $jsonBody['stop_sequences'] = $options['stopSequences'];
        }
        if (!empty($options['temperature'])) {
            $jsonBody['temperature'] = $options['temperature'];
        }
        if (!empty($options['topP'])) {
            $jsonBody['top_p'] = $options['topP'];
        }
        if (!empty($options['topK'])) {
            $jsonBody['top_k'] = $options['topK'];
        }

        // Force streaming for this method
        if ($stream) {
            $jsonBody['stream'] = true;
        }

        try {
            return $this->client->post($url, [
                'headers' => [
                    'x-api-key' => $options['apiKey'],
                    'anthropic-version' => $options['apiVersion'],
                    'Content-Type' => 'application/json',
                ],
                'json' => $jsonBody,
                'stream' => $stream,
            ]);
        } catch (GuzzleException $e) {
            LogUtility::logException($options);
            throw $e;
        }
    }
}
