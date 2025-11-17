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
 * Class OpenAIClient
 */
class OpenAIClient implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const API_ENDPOINT = 'https://api.openai.com/v1/responses';

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
     * @throws GuzzleException
     */
    public function generateResponse(string $prompt, array $options = [], bool $stream = false): ResponseInterface
    {
        $url = self::API_ENDPOINT;

        $requestBody = [
            'model' => $options['model'],
            'input' => $prompt,
            'temperature' => $options['temperature'],
            'top_p' => $options['topP'],
            'max_output_tokens' => $options['max_output_tokens'],
        ];

        if (!empty($options['stop'])) {
            $requestBody['stop'] = $options['stop'];
        }

        // Force streaming for this method
        if ($stream) {
            $requestBody['stream'] = true;
        }

        try {
            return $this->client->post($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $options['apiKey'],
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestBody,
                'stream' => $stream,
            ]);
        } catch (GuzzleException $e) {
            LogUtility::logException($options);
            throw $e;
        }
    }
}
