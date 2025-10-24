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
 * Class CohereClient
 */
class CohereClient implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const API_ENDPOINT = 'https://api.cohere.ai/v1/chat';

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
    public function generateResponse(string $prompt, array $options = [], bool $stream = false): string|ResponseInterface
    {
        $url = self::API_ENDPOINT;

        $jsonBody = [
            'model' => $options['model'],
            'message' => $prompt, // The user's message to send to the model. Can be used instead of chat_history
        ];

        if (!empty($options['chatHistory'])) {
            $jsonBody['chat_history'] = $options['chatHistory'];
        }
        if (!empty($options['maxTokens'])) {
            $jsonBody['max_tokens'] = $options['maxTokens'];
        }
        if (isset($options['temperature'])) {
            $jsonBody['temperature'] = $options['temperature'];
        }
        if (isset($options['p'])) {
            $jsonBody['p'] = $options['p'];
        }
        if (isset($options['k'])) {
            $jsonBody['k'] = $options['k'];
        }
        if (isset($options['frequencyPenalty'])) {
            $jsonBody['frequency_penalty'] = $options['frequencyPenalty'];
        }
        if (isset($options['presencePenalty'])) {
            $jsonBody['presence_penalty'] = $options['presencePenalty'];
        }
        if (!empty($options['stopSequences'])) {
            $jsonBody['stop_sequences'] = $options['stopSequences'];
        }
        if (isset($options['stream'])) {
            $jsonBody['stream'] = $options['stream'];
        }
        if (!empty($options['preamble'])) {
            $jsonBody['preamble'] = $options['preamble'];
        }

        try {
            return $this->client->post($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $options['apiKey'],
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
