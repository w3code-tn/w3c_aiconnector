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
 * Class GeminiClient
 */
class GeminiClient implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/';
    private const API_URL_SUFFIX = ':generateContent?key=';
    private const API_URL_STREAM_SUFFIX = ':streamGenerateContent?key=';

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
    public function generateResponse(string $prompt, array $options = [], bool $stream = false): ResponseInterface
    {
        $url = self::API_URL . $options['model'] . (
            $stream
                ? self::API_URL_STREAM_SUFFIX
                : self::API_URL_SUFFIX
        ) . $options['apiKey'];

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
        ];

        if (!empty($options['generationConfig'])) {
            $payload['generationConfig'] = $options['generationConfig'];
        }

        try {
            return $this->client->post($url, [
                'json' => $payload,
                'stream' => $stream,
            ]);
        } catch (GuzzleException $e) {
            LogUtility::logException($options);
            throw $e;
        }
    }
}
