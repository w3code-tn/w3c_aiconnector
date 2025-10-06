<?php

declare(strict_types=1);

namespace W3code\W3cAIConnector\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerAwareTrait;
use W3code\W3cAIConnector\Utility\ConfigurationUtility;

/**
 * Class OllamaClient
 */
class OllamaClient
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
                        if (json_last_error() === JSON_ERROR_NONE && isset($data['response'])) {
                            yield $data['response'];
                        }
                    }
                }
            }
            // After loop, if there's any remaining buffer, try to process it as a final chunk
            if (trim($buffer) !== '') {
                $data = json_decode($buffer, true);
                $this->logger->info('Ollama stream final buffer: ' . $buffer, ['model' => $options['model'], 'options' => $logOptions]);
                if (json_last_error() === JSON_ERROR_NONE && isset($data['response'])) {
                    yield $data['response'];
                }
            }
        } else {
            $body = json_decode($response->getBody()->getContents(), true);
            return $body['response'] ?? null;
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
        $requestBody = [
            'model' => $options['model'],
            'prompt' => $prompt,
            'stream' => false,
        ];

        $ollamaOptions = [];
        if ($options['temperature'] !== ConfigurationUtility::getDefaultConfiguration('ollamaTemperature'))
            $ollamaOptions['temperature'] = $options['temperature'];
        if ($options['topP'] !== ConfigurationUtility::getDefaultConfiguration('ollamaTopP'))
            $ollamaOptions['top_p'] = $options['topP'];
        if ($options['numPredict'] !== ConfigurationUtility::getDefaultConfiguration('ollamaNumPredict'))
            $ollamaOptions['num_predict'] = $options['numPredict'];
        if (!empty($options['stop'])) $ollamaOptions['stop'] = $options['stop'];
        if (!empty($ollamaOptions)) $requestBody['options'] = $ollamaOptions;
        if (!empty($options['format'])) $requestBody['format'] = $options['format'];
        if (!empty($options['system'])) $requestBody['system'] = $options['system'];

        return $this->client->post($options['endPoint'] . '/api/generate', [
            'headers' => [
                'Expect' => ''
            ],
            'json' => $requestBody,
            'timeout' => 300,
        ]);
    }
}
