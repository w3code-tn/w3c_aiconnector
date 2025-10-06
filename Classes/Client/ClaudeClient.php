<?php

declare(strict_types=1);

namespace W3code\W3cAIConnector\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

/**
 * Class ClaudeClient
 */
class ClaudeClient
{
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
                $buffer .= $body->read(1024);
                while (($pos = strpos($buffer, "\n\n")) !== false) {
                    $eventData = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 2);

                    $event = null;
                    $data = null;

                    foreach (explode("\n", $eventData) as $line) {
                        if (str_starts_with($line, 'event: ')) {
                            $event = trim(substr($line, 7));
                        } elseif (str_starts_with($line, 'data: ')) {
                            $data = trim(substr($line, 6));
                        }
                    }

                    if ($event === 'content_block_delta') {
                        $json = json_decode($data, true);
                        if (isset($json['delta']['text'])) {
                            yield $json['delta']['text'];
                        }
                    }
                }
            }
        } else {
            $body = json_decode((string)$response->getBody(), true);
            // Adapt the key according to Claude’s response.
            return $body['content'][0]['text'] ?? null;
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
            'max_tokens' => $options['maxTokens'],
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ]
        ];

        if(!empty($options['system'])) $jsonBody['system'] = $options['system'];
        if(!empty($options['stopSequences'])) $jsonBody['stop_sequences'] = $options['stopSequences'];
        if(!empty($options['temperature'])) $jsonBody['temperature'] = $options['temperature'];
        if(!empty($options['topP'])) $jsonBody['top_p'] = $options['topP'];
        if(!empty($options['topK'])) $jsonBody['top_k'] = $options['topK'];

        return $this->client->post($url, [
            'headers' => [
                'x-api-key' => $options['apiKey'],
                'anthropic-version' => $options['apiVersion'],
                'Content-Type' => 'application/json'
            ],
            'json' => $jsonBody,
            'stream' => $stream,
        ]);
    }
}
