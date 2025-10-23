<?php

declare(strict_types=1);

namespace W3code\W3cAIConnector\Provider;

use Generator;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use W3code\W3cAIConnector\Client\GeminiClient;
use W3code\W3cAIConnector\Utility\LocalizationUtility;

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
     *
     * @return void
     */
    public function setup(): void
    {
        $config = $this->extConfig['gemini'];
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
            'fallbacks' => $this->getFallbackModels($config['fallbackModels'] ?? '')
        ];
    }

    /**
     * process the response from the AI provider
     *
     * @param string $prompt
     * @param array $options
     * @param int $retryCount
     * @param bool $stream
     *
     * @return string|Generator
     */
    public function process(string $prompt, array $options = [], int &$retryCount = 0, bool $stream = false): string|Generator
    {
        parent::process($prompt, $options, $retryCount, $stream);

        $logOptions = $options;
        $this->logger->info(
            ucfirst($options['model']) . ($stream ? ' stream ' : ' ') . 'info: ',
            ['model' => $options['model'], 'options' => $logOptions]
        );

        try {
            if($stream) {
                yield $this->client->getContent($prompt, $options, $stream);
            } else {
                return $this->client->getContent($prompt, $options, $stream);
            }
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                if (($statusCode === 429 || $statusCode === 503) && $retryCount < $this->config['maxRetries']) {
                    $retryCount++;
                    $this->logger->warning('Gemini' . $statusCode . 'error', ['model' => $options['model'], 'options' => $logOptions]);
                    $options['model'] = $this->fallbackToModel('gemini', $options['model']);
                    $this->process($prompt, $options);
                }
            }
            $this->handleServiceRequestException('Gemini', $e, $logOptions, $options['model']);
            return '{error: "Gemini - ' . LocalizationUtility::translate('not_available') . '"}';
        } catch (GuzzleException $e) {
            $this->handleServiceGuzzleException('Gemini', $e, $logOptions, $options['model']);
            return '{error: "Gemini - ' .  LocalizationUtility::translate('not_available') . '"}';
        }
    }

    /**
     * return the current configuration of the provider
     *
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}
