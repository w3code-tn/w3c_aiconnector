<?php

declare(strict_types=1);

namespace W3code\W3cAIConnector\Provider;

use Generator;
use W3code\W3cAIConnector\Client\CohereClient;

class CohereProvider extends AbstractProvider
{
    private const PROVIDER_NAME = 'cohere';
    protected ?CohereClient $client = null;

    public function __construct()
    {
        parent::__construct();

        $this->client = new CohereClient();
        $this->setup();
    }

    /**
     * Set all configuration needed for the AI model provider
     */
    public function setup(): void
    {
        $config = $this->extConfig[self::PROVIDER_NAME];
        $this->config = [
            'apiKey' => $config['apiKey'],
            'model' => $config['modelName'],
            'maxTokens' => (int)$config['maxTokens'],
            'temperature' => (float)$config['temperature'],
            'p' => (float)$config['p'],
            'k' => (int)$config['k'],
            'frequencyPenalty' => (float)$config['frequencyPenalty'],
            'presencePenalty' => (float)$config['presencePenalty'],
            'stopSequences' => empty($config['stopSequences']) ? [] : explode(',', $config['stopSequences']),
            'stream' => (bool)$config['stream'],
            'preamble' => $config['preamble'],
            'chatHistory' => [], // Cohere expects an array of messages for chat_history
            'chunkSize' => (int)$config['chunkSize'],
            'maxInputTokensAllowed' => (int)$config['maxInputTokensAllowed'],
            'maxRetries' => (int)$config['maxRetries'],
            'fallbacks' => $this->getFallbackModels($config['fallbackModels']),
        ];
    }

    /**
     * process the response from the AI provider
     *
     * @param string $prompt
     * @param array $options
     *
     * @return string
     */
    public function process(string $prompt, array $options = []): string
    {
        return $this->handleProcess(
            function ($prompt, $options) {
                $response = $this->client->generateResponse($prompt, $options);
                $body = json_decode((string)$response->getBody(), true);
                return $body['text'] ?? null;
            },
            $prompt,
            self::PROVIDER_NAME,
            $options
        );
    }

    /**
     * process the response from the AI provider in streaming mode
     *
     * @param string $prompt
     * @param array $options
     * @return Generator
     */
    public function processStream(string $prompt, array $options = []): Generator
    {
        yield from $this->handleProcess(
            function ($prompt, $options) {
                $response = $this->client->generateResponse($prompt, $options, true);

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
            },
            $prompt,
            self::PROVIDER_NAME,
            $options,
            true
        );
    }
}
