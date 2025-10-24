<?php

declare(strict_types=1);

namespace W3code\W3cAIConnector\Provider;

use Generator;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use W3code\W3cAIConnector\Client\OpenAIClient;

class OpenAIProvider extends AbstractProvider
{
    private const PROVIDER_NAME = 'openai';
    protected ?OpenAIClient $client = null;

    public function __construct()
    {
        parent::__construct();

        $this->client = new OpenAIClient();
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
            'temperature' => (float)$config['temperature'],
            'topP' => (float)$config['topP'],
            'max_output_tokens' => (int)$config['maxOutputTokens'],
            'stop' => GeneralUtility::trimExplode(',', $config['stop'], true),
            'stream' => (bool)$config['stream'],
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
        yield from $this->handleProcess(function ($prompt, $options) {
            $response = $this->client->generateResponse($prompt, $options, true);

            $body = $response->getBody();
            $buffer = '';
            while (!$body->eof()) {
                $buffer .= $body->read(1024);
                while (($pos = strpos($buffer, "\n\n")) !== false) {
                    $eventData = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 2);

                    foreach (explode("\n", $eventData) as $line) {
                        if (str_starts_with($line, 'data: ')) {
                            $json = trim(substr($line, 5));
                            if ($json === '[DONE]') {
                                break 2; // Exit both loops
                            }
                            $data = json_decode($json, true);
                            if (isset($data['choices'][0]['delta']['content'])) {
                                yield $data['choices'][0]['delta']['content'];
                            }
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
