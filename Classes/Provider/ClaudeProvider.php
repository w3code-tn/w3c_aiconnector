<?php

declare(strict_types=1);

namespace W3code\W3cAIConnector\Provider;

use Generator;
use W3code\W3cAIConnector\Client\ClaudeClient;

/**
 * Class ClaudeProvider
 */
class ClaudeProvider extends AbstractProvider
{
    private const PROVIDER_NAME = 'claude';
    protected ?ClaudeClient $client = null;

    public function __construct()
    {
        parent::__construct();

        $this->client = new ClaudeClient();
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
            'apiVersion' => $config['apiVersion'],
            'maxTokens' => (int)$config['maxTokens'],
            'system' => $config['system'],
            'stopSequences' => empty($config['stopSequences']) ? [] : explode(',', $config['stopSequences']),
            'stream' => (bool)$config['stream'],
            'temperature' => (float)$config['temperature'],
            'topP' => (float)$config['topP'],
            'topK' => (int)$config['topK'],
            'chunkSize' => (int)$config['chunkSize'],
            'maxInputTokensAllowed' => (int)$config['maxInputTokensAllowed'],
            'maxRetries' => (int)$this->extConfig['maxRetries'],
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
                return $body['content'][0]['text'] ?? null;
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
            },
            $prompt,
            self::PROVIDER_NAME,
            $options,
            true
        );
    }
}
