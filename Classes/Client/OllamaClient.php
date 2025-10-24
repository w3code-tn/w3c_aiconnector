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
 * Class OllamaClient
 */
class OllamaClient implements LoggerAwareInterface
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
     * @throws GuzzleException
     */
    public function generateResponse(string $prompt, array $options = [], bool $stream = false): ResponseInterface
    {
        $requestBody = [
            'model' => $options['model'],
            'prompt' => $prompt,
            'stream' => $stream,
        ];

        $ollamaOptions['temperature'] = $options['temperature'];
        $ollamaOptions['top_p'] = $options['topP'];
        $ollamaOptions['num_predict'] = $options['numPredict'];

        if (!empty($options['stop'])) {
            $ollamaOptions['stop'] = $options['stop'];
        }
        if (!empty($ollamaOptions)) {
            $requestBody['options'] = $ollamaOptions;
        }
        if (!empty($options['format'])) {
            $requestBody['format'] = $options['format'];
        }
        if (!empty($options['system'])) {
            $requestBody['system'] = $options['system'];
        }

        try {
            return $this->client->post($options['endPoint'] . '/api/generate', [
                'json' => $requestBody,
                'timeout' => 300,
            ]);
        } catch (GuzzleException $e) {
            LogUtility::logException($options);
            throw $e;
        }
    }
}
