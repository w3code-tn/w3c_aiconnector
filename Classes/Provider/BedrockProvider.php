<?php

declare(strict_types=1);

namespace W3code\W3cAIConnector\Provider;

use Aws\BedrockRuntime\BedrockRuntimeClient;
use Aws\Exception\AwsException;
use Generator;
use Throwable;
use W3code\W3cAIConnector\Utility\LogUtility;

/**
 * Class BedrockProvider
 */
class BedrockProvider extends AbstractProvider
{
    private const PROVIDER_NAME = 'bedrock';
    protected ?BedrockRuntimeClient $client = null;

    public function __construct()
    {
        parent::__construct();
        $this->setup();
    }

    public function setup(): void
    {
        $config = $this->extConfig[self::PROVIDER_NAME];
        $accessKeyId = $config['accessKeyId'] ?? '';
        $secretAccessKey = $config['secretAccessKey'] ?? '';

        if (str_starts_with((string)$secretAccessKey, 'ABSK')) {
            $decoded = base64_decode(substr((string)$secretAccessKey, 4));
            if ($decoded !== false && str_contains($decoded, ':')) {
                [$key, $secret] = explode(':', $decoded, 2);
                $accessKeyId = $key;
                $secretAccessKey = $secret;
            }
        }

        $this->config = [
            'accessKeyId' => $accessKeyId,
            'secretAccessKey' => $secretAccessKey,
            'region' => $config['region'] ?? 'us-east-1',
            'model' => $config['modelId'],
            'maxTokens' => (int)$config['maxTokens'],
            'temperature' => (float)$config['temperature'],
            'chunkSize' => (int)$config['chunkSize'],
            'maxInputTokensAllowed' => (int)$config['maxInputTokensAllowed'],
            'maxRetries' => (int)$this->extConfig['maxRetries'],
            'fallbacks' => $this->getFallbackModels($config['fallbackModels'] ?? ''),
        ];

        if (!empty($this->config['accessKeyId']) && !empty($this->config['secretAccessKey'])) {
            $this->client = new BedrockRuntimeClient([
                'region' => $this->config['region'],
                'version' => 'latest',
                'credentials' => [
                    'key' => $this->config['accessKeyId'],
                    'secret' => $this->config['secretAccessKey'],
                ],
            ]);
        }
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
        $options = array_replace_recursive($this->config, $options);

        if (!$this->client) {
            return '{error: "Bedrock - provider not available (check credentials)"}';
        }

        try {
            $body = $this->prepareRequestBody($prompt, $options);
            $result = $this->client->invokeModel([
                'modelId' => $options['model'],
                'contentType' => 'application/json',
                'accept' => 'application/json',
                'body' => json_encode($body),
            ]);

            $responseBody = json_decode($result['body']->getContents(), true);
            return $this->extractResponseText($responseBody, $options['model']) ?? '';
        } catch (AwsException $e) {
            return $this->handleAwsException($e, $prompt, $options);
        } catch (Throwable $e) {
            LogUtility::logException($options, $e->getMessage());
            return '{error: "Bedrock - ' . $e->getMessage() . '"}';
        }
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
        $options = array_replace_recursive($this->config, $options);

        if (!$this->client) {
            yield 'Bedrock - provider not available (check credentials)';
            return;
        }

        try {
            $body = $this->prepareRequestBody($prompt, $options);
            $result = $this->client->invokeModelWithResponseStream([
                'modelId' => $options['model'],
                'contentType' => 'application/json',
                'accept' => 'application/json',
                'body' => json_encode($body),
            ]);

            foreach ($result['body'] as $event) {
                if (isset($event['chunk'])) {
                    $chunk = json_decode($event['chunk']['bytes'], true);
                    $text = $this->extractStreamResponseText($chunk, $options['model']);
                    if ($text) {
                        yield $text;
                    }
                }
            }
        } catch (AwsException $e) {
            yield from $this->handleAwsExceptionStream($e, $prompt, $options);
        } catch (Throwable $e) {
            LogUtility::logException($options, $e->getMessage());
            yield 'Bedrock - ' . $e->getMessage();
        }
    }

    protected function handleAwsException(AwsException $e, string $prompt, array $options): string
    {
        if ($this->retryCount < $this->config['maxRetries'] && !empty($this->config['fallbacks'][$options['model']])) {
            $this->retryCount++;
            $options['model'] = $this->fallbackToModel($options['model']);
            return $this->process($prompt, $options);
        }

        LogUtility::logException($options, $e->getMessage());
        return '{error: "Bedrock - ' . ($e->getAwsErrorMessage() ?: $e->getMessage()) . '"}';
    }

    protected function handleAwsExceptionStream(AwsException $e, string $prompt, array $options): Generator
    {
        if ($this->retryCount < $this->config['maxRetries'] && !empty($this->config['fallbacks'][$options['model']])) {
            $this->retryCount++;
            $options['model'] = $this->fallbackToModel($options['model']);
            yield from $this->processStream($prompt, $options);
            return;
        }

        LogUtility::logException($options, $e->getMessage());
        yield '{error: "Bedrock - ' . ($e->getAwsErrorMessage() ?: $e->getMessage()) . '"}';
    }

    protected function prepareRequestBody(string $prompt, array $options): array
    {
        $modelId = $options['model'];

        // Anthropic Claude
        if (str_contains($modelId, 'anthropic.claude')) {
            return [
                'anthropic_version' => 'bedrock-2023-05-31',
                'max_tokens' => $options['maxTokens'],
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => $prompt,
                            ],
                        ],
                    ],
                ],
                'temperature' => $options['temperature'],
            ];
        }

        // Meta Llama
        if (str_contains($modelId, 'meta.llama')) {
            return [
                'prompt' => $prompt,
                'max_gen_len' => $options['maxTokens'],
                'temperature' => $options['temperature'],
            ];
        }

        // Default / Generic (Amazon Titan etc.)
        return [
            'inputText' => $prompt,
            'textGenerationConfig' => [
                'maxTokenCount' => $options['maxTokens'],
                'temperature' => $options['temperature'],
            ],
        ];
    }

    protected function extractResponseText(array $body, string $modelId): ?string
    {
        if (str_contains($modelId, 'anthropic.claude')) {
            return $body['content'][0]['text'] ?? null;
        }
        if (str_contains($modelId, 'meta.llama')) {
            return $body['generation'] ?? null;
        }
        return $body['results'][0]['outputText'] ?? null;
    }

    protected function extractStreamResponseText(array $chunk, string $modelId): ?string
    {
        if (str_contains($modelId, 'anthropic.claude')) {
            return $chunk['delta']['text'] ?? null;
        }
        if (str_contains($modelId, 'meta.llama')) {
            return $chunk['generation'] ?? null;
        }
        return $chunk['outputText'] ?? null;
    }
}
