<?php

declare(strict_types=1);

namespace W3code\W3cAIConnector\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

/**
 * Class CohereClient
 */
class CohereClient
{
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
     * @return mixed
     * @throws GuzzleException
     */
    public function getContent(string $prompt, array $options = [], bool $stream = false): mixed
    {
        $response = $this->generateResponse($prompt, $options, $stream);

        if($stream) {
            // @todo: better use more generic functions to make the code more readable
            $body = $response->getBody();
            $buffer = '';
            while (!$body->eof()) {
                $buffer .= $body->read($options['chunkSize']); // Read a larger chunk into buffer
                while (($newlinePos = strpos($buffer, "\n")) !== false) {
                    $line = substr($buffer, 0, $newlinePos);
                    $buffer = substr($buffer, $newlinePos + 1); // Remove processed line from buffer

                    // Only process non-empty lines that might contain JSON
                    if (trim($line) !== '') {
                        $data = json_decode($line, true);
                        // Check for JSON decoding errors and the expected structure
                        if (json_last_error() === JSON_ERROR_NONE && isset($data['event_type']) && $data['event_type'] === 'text-generation' && isset($data['text'])) {
                            yield $data['text'];
                        }
                    }
                }
            }
            // After loop, if there's any remaining buffer, try to process it as a final chunk
            if (trim($buffer) !== '') {
                $data = json_decode($buffer, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($data['event_type']) && $data['event_type'] === 'text-generation' && isset($data['text'])) {
                    yield $data['text'];
                }
            }
        } else {
            $body = json_decode((string)$response->getBody(), true);
            return $body['text'] ?? null;
        }
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

        $jsonBody = [
            'model' => $options['model'],
            'message' => $prompt, // The user's message to send to the model. Can be used instead of chat_history
        ];

        if (!empty($options['chatHistory'])) $jsonBody['chat_history'] = $options['chatHistory'];
        if (!empty($options['maxTokens'])) $jsonBody['max_tokens'] = $options['maxTokens'];
        if (isset($options['temperature'])) $jsonBody['temperature'] = $options['temperature'];
        if (isset($options['p'])) $jsonBody['p'] = $options['p'];
        if (isset($options['k'])) $jsonBody['k'] = $options['k'];
        if (isset($options['frequencyPenalty'])) $jsonBody['frequency_penalty'] = $options['frequencyPenalty'];
        if (isset($options['presencePenalty'])) $jsonBody['presence_penalty'] = $options['presencePenalty'];
        if (!empty($options['stopSequences'])) $jsonBody['stop_sequences'] = $options['stopSequences'];
        if (isset($options['stream'])) $jsonBody['stream'] = $options['stream'];
        if (!empty($options['preamble'])) $jsonBody['preamble'] = $options['preamble'];

        return $this->client->post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $options['apiKey'],
                'Content-Type' => 'application/json'
            ],
            'json' => $jsonBody,
            'stream' => $stream,
        ]);
    }
}
