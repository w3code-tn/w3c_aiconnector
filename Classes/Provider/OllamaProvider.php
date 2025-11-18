<?php

declare(strict_types=1);

namespace W3code\W3cAIConnector\Provider;

use Generator;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use W3code\W3cAIConnector\Client\OllamaClient;

class OllamaProvider extends AbstractProvider
{
    private const PROVIDER_NAME = 'ollama';
    protected ?OllamaClient $client = null;

    public function __construct()
    {
        parent::__construct();

        $this->client = new OllamaClient();
        $this->setup();
    }

    /**
     * Set all configuration needed for the AI model provider
     */
    public function setup(): void
    {
        $config = $this->extConfig[self::PROVIDER_NAME];
        $this->config = [
            'model' => $config['modelName'],
            'endPoint' => $config['endPoint'],
            'stream' => (bool)$config['stream'],
            'temperature' => (float)$config['temperature'],
            'topP' => (float)$config['topP'],
            'numPredict' => (int)$config['numPredict'],
            'stop' => empty($config['stop']) ? [] : GeneralUtility::trimExplode(',', $config['stop'], true),
            'format' => $config['format'],
            'system' => $config['system'],
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
                $body = json_decode((string)$response->getBody()->getContents(), true);
                return $body['response'] ?? null;
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
                            if (json_last_error() === JSON_ERROR_NONE && isset($data['response'])) {
                                yield $data['response'];
                            }
                        }
                    }
                }
                // After loop, if there's any remaining buffer, try to process it as a final chunk
                if (trim($buffer) !== '') {
                    $data = json_decode($buffer, true);

                    $logOptions = $options;
                    $this->logger->info('Ollama stream final buffer: ' . $buffer, ['model' => $options['model'], 'options' => $logOptions]);
                    if (json_last_error() === JSON_ERROR_NONE && isset($data['response'])) {
                        yield $data['response'];
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
