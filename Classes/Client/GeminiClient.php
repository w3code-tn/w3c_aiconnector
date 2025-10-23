<?php

declare(strict_types=1);

namespace W3code\W3cAIConnector\Client;

use Generator;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

/**
 * Class GeminiClient
 */
class GeminiClient
{
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
     * @return string|Generator
     * @throws GuzzleException
     */
    public function getContent(string $prompt, array $options = [], bool $stream = false): string|Generator
    {
        $response = $this->generateResponse($prompt, $options, $stream);

        if($stream) {
            // @todo: better use more generic functions to make the code more readable
            $body = $response->getBody();
            $buffer = '';

            while (!$body->eof()) {
                $buffer .= $body->read($options['chunkSize'] ?? 2048);

                // NOUVELLE LOGIQUE : On cherche des objets JSON complets dans le buffer
                // On considère qu'un objet se termine par '}'. On split donc par ce caractère.

                $tempBuffer = substr($buffer, strpos($buffer, '{'));
                $potentialObjects = explode('}',  $tempBuffer);
                $lastElement = array_pop($potentialObjects);
                $potentialJson = implode('}', $potentialObjects).'}';

                if(json_validate($potentialJson)) {
                    // Le dernier élément du tableau est ce qui reste après le dernier '}'.
                    // C'est soit une chaîne vide, soit un début d'objet JSON. On le garde pour la prochaine itération.
                    $buffer = $lastElement;

                    $decoded = json_decode($potentialJson, true);

                    if (json_last_error() === JSON_ERROR_NONE) {
                        // C'est un JSON valide ! On l'exploite.
                        if (isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
                            yield $decoded['candidates'][0]['content']['parts'][0]['text'];
                        }

                        // La condition pour arrêter reste la même et est toujours aussi importante.
                        if (isset($decoded['usageMetadata'])) {
                            //  break; // Sortir de la boucle foreach ET de la boucle while
                        }
                    }
                }
            }
        } else {
            $body = json_decode((string)$response->getBody(), true);
            return $body['candidates'][0]['content']['parts'][0]['text'];
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
        $url = self::API_URL . $options['model'] . (
            $stream
                ? self::API_URL_STREAM_SUFFIX
                : self::API_URL_SUFFIX
        ) . $options['apiKey'];

        $payload = [
            'contents' => [
                ['parts' => [['text' => $prompt]]]
            ],
            'generationConfig' => $options['generationConfig']
        ];

        if($stream)
            $payload['contents']['role'] = 'user';

        return $this->client->post($url, [
            'json' => $payload,
            'stream' => $stream,
        ]);
    }
}
