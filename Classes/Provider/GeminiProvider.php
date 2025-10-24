<?php

declare(strict_types=1);

namespace W3code\W3cAIConnector\Provider;

use Generator;
use W3code\W3cAIConnector\Client\GeminiClient;

/**
 * Class GeminiProvider
 */
class GeminiProvider extends AbstractProvider
{
    private const PROVIDER_NAME = 'gemini';
    protected ?GeminiClient $client = null;

    public function __construct()
    {
        parent::__construct();

        $this->client = new GeminiClient();
        $this->setup();
    }

    /**
     * sets the configuration for the AI provider
     */
    public function setup(): void
    {
        $config = $this->extConfig[self::PROVIDER_NAME];
        $this->config = [
            'apiKey' => $config['apiKey'],
            'model' => $config['modelName'],
            'generationConfig' => [
                'temperature' => (float)$config['temperature'],
                'topP' => (float)$config['topP'],
                'topK' => (int)$config['topK'],
                'candidateCount' => (int)$config['candidateCount'],
                'maxOutputTokens' => (int)$config['maxOutputTokens'],
                'stopSequences' => empty($config['stopSequences']) ? [] : explode(',', $config['stopSequences']),
            ],
            'chunkSize' => (int)$config['chunkSize'],
            'maxInputTokensAllowed' => (int)$config['maxInputTokensAllowed'],
            'maxRetries' => (int)$config['maxRetries'],
            'fallbacks' => $this->getFallbackModels($config['fallbackModels'] ?? ''),
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
                return $body['candidates'][0]['content']['parts'][0]['text'] ?? '';
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
                    $buffer .= $body->read($options['chunkSize'] ?? 2048);
                    $tempBuffer = substr($buffer, strpos($buffer, '{'));
                    $potentialObjects = explode('}', $tempBuffer);
                    $lastElement = array_pop($potentialObjects);
                    $potentialJson = implode('}', $potentialObjects) . '}';

                    if (json_validate($potentialJson)) {
                        $buffer = $lastElement;
                        $decoded = json_decode($potentialJson, true);

                        if (isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
                            yield $decoded['candidates'][0]['content']['parts'][0]['text'];
                        }

                        if (isset($decoded['usageMetadata'])) {
                            // @todo: optional break if needed
                        }
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
