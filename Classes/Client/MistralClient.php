<?php

declare(strict_types=1);

namespace W3code\W3cAIConnector\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

/**
 * Class MistralClient
 */
class MistralClient
{
    private const API_ENDPOINT = 'https://api.mistral.ai/v1/chat/completions';

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
                $buffer .= $body->read($options['chunkSize']);
                while (($pos = strpos($buffer, "\n\n")) !== false) {
                    $eventData = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 2);

                    foreach (explode("\n", $eventData) as $line) {
                        if (str_starts_with($line, 'data: ')) {
                            $json = trim(substr($line, 5));
                            if ($json === '[DONE]') {
                                break 2; // Break out of both while loops
                            }
                            $data = json_decode($json, true);
                            if (isset($data['choices'][0]['delta']['content'])) {
                                yield $data['choices'][0]['delta']['content'];
                            }
                        }
                    }
                }
            }
        } else {
            $body = json_decode((string)$response->getBody(), true);
            return $body['choices'][0]['message']['content'] ?? null;
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
        $requestBody = [
            'model' => $options['model'],
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => $options['temperature'],
            'top_p' => $options['topP'],
            'max_tokens' => $options['maxTokens'],
            'random_seed' => $options['randomSeed'],
            'stream' => $stream,
            'safe_prompt' => $options['safePrompt'],
        ];

        if (!empty($options['stop'])) $requestBody['stop'] = $options['stop'];

        return $this->client->post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $options['apiKey'],
                'Content-Type' => 'application/json'
            ],
            'json' => $requestBody,
            $stream => $stream
        ]);
    }
}
