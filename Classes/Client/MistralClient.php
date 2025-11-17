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
 * Class MistralClient
 */
class MistralClient implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const API_ENDPOINT = 'https://api.mistral.ai/v1/chat/completions';

    protected ?Client $client = null;

    public function __construct()
    {
        $this->client = new Client();
    }

    public function setClient(Client $client): void
    {
        $this->client = $client;
    }

    /**
     * @param string $prompt
     * @param array $options
     * @param bool $stream
     *
     * @return ResponseInterface
     * @throws GuzzleException
     */
    public function generateResponse(string $prompt, array $options = [], bool $stream = false): ResponseInterface
    {
        $url = self::API_ENDPOINT;
        $requestBody = [
            'model' => $options['model'],
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'temperature' => $options['temperature'],
            'top_p' => $options['topP'],
            'max_tokens' => $options['maxTokens'],
            'random_seed' => $options['randomSeed'],
            'stream' => $stream,
            'safe_prompt' => $options['safePrompt'],
        ];

        if (!empty($options['stop'])) {
            $requestBody['stop'] = $options['stop'];
        }

        try {
            return $this->client->post($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $options['apiKey'],
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestBody,
                $stream => $stream,
            ]);
        } catch (GuzzleException $e) {
            LogUtility::logException($options);
            throw $e;
        }
    }
}
