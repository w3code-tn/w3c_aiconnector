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
 * Class GoogleTranslateClient
 */
class GoogleTranslateClient implements LoggerAwareInterface
{
    use LoggerAwareTrait;

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
     * @return ResponseInterface
     * @throws GuzzleException
     */
    public function generateResponse(string $prompt, array $options = []): ResponseInterface
    {
        $requestBody = [
            'q' => $prompt,
            'target' => $options['targetLang'],
        ];

        if (!empty($options['sourceLang'])) {
            $requestBody['source'] = $options['sourceLang'];
        }
        if (!empty($options['format'])) {
            $requestBody['format'] = $options['format'];
        }
        if (!empty($options['model'])) {
            $requestBody['model'] = $options['model'];
        }
        if (!empty($options['cid'])) {
            $requestBody['cid'] = $options['cid'];
        }

        try {
            return $this->client->post(self::API_ENDPOINT . '?key=' . $options['apiKey'], [
                'json' => $requestBody,
            ]);
        } catch (GuzzleException $e) {
            LogUtility::logException($options);
            throw $e;
        }
    }
}
