<?php

declare(strict_types=1);

namespace W3code\W3cAIConnector\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

/**
 * Class GoogleTranslateClient
 */
class GoogleTranslateClient
{
    private const API_ENDPOINT = 'https://translation.googleapis.com/language/translate/v2';

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
     * @return mixed
     * @throws GuzzleException
     */
    public function getContent(string $prompt, array $options = [], bool $stream = false): mixed
    {
        $response = $this->generateResponse($prompt, $options, $stream);

        $body = json_decode((string)$response->getBody(), true);
        return $body['data']['translations'][0]['translatedText'] ?? null;
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
        $requestBody = [
            'q' => $prompt,
            'target' => $options['targetLang'],
        ];

        if (!empty($options['sourceLang'])) $requestBody['source'] = $options['sourceLang'];
        if (!empty($options['format'])) $requestBody['format'] = $options['format'];
        if (!empty($options['model'])) $requestBody['model'] = $options['model'];
        if (!empty($options['cid'])) $requestBody['cid'] = $options['cid'];

        return $this->client->post(self::API_ENDPOINT . '?key=' . $options['apiKey'], [
            'json' => $requestBody
        ]);
    }
}
