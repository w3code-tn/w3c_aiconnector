<?php

declare(strict_types=1);

namespace W3code\W3cAIConnector\Provider;

use Generator;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerAwareTrait;
use W3code\W3cAIConnector\Client\ClaudeClient;
use W3code\W3cAIConnector\Utility\ConfigurationUtility;
use W3code\W3cAIConnector\Utility\LocalizationUtility;

/**
 * Class ClaudeProvider
 */
class ClaudeProvider extends AbstractProvider
{
    use LoggerAwareTrait;

    protected ?ClaudeClient $client = null;

    /**
     * Set all configuration needed for the AI model provider
     *
     * @return void
     */
    public function setConfig(): void
    {
        $this->config = [
            'apiKey' => $this->extConfig['claudeApiKey'] ?? '',
            'model' => $this->extConfig['claudeModelName'] ?? ConfigurationUtility::getDefaultConfiguration('claudeModelName'),
            'apiVersion' => $this->extConfig['claudeApiVersion']
                ?? ConfigurationUtility::getDefaultConfiguration('claudeApiVersion'),
            'maxTokens' => (int)($this->extConfig['claudeMaxTokens']
                ?? ConfigurationUtility::getDefaultConfiguration('claudeMaxTokens')),
            'system' => $this->extConfig['claudeSystem']
                ?? ConfigurationUtility::getDefaultConfiguration('claudeSystem'),
            'stopSequences' => $this->extConfig['claudeStopSequences']
                ? explode(',', $this->extConfig['claudeStopSequences'])
                : ConfigurationUtility::getDefaultConfiguration('claudeStopSequences'),
            'stream' => (bool)($this->extConfig['claudeStream']
                ?? ConfigurationUtility::getDefaultConfiguration('claudeStream')),
            'temperature' => (float)($this->extConfig['claudeTemperature']
                ?? ConfigurationUtility::getDefaultConfiguration('claudeTemperature')),
            'topP' => (float)($this->extConfig['claudeTopP']
                ?? ConfigurationUtility::getDefaultConfiguration('claudeTopP')),
            'topK' => (int)($this->extConfig['claudeTopK']
                ?? ConfigurationUtility::getDefaultConfiguration('claudeTopK')),
            'chunkSize' => (int)($this->extConfig['claudeChunkSize']
                ?? ConfigurationUtility::getDefaultConfiguration('claudeChunkSize')),
            'maxInputTokensAllowed' => (int)($this->extConfig['claudeMaxInputTokensAllowed']
                ?? ConfigurationUtility::getDefaultConfiguration('maxInputTokensAllowed')),
            'maxRetries' => (int)($this->extConfig['maxRetries']
                ?? ConfigurationUtility::getDefaultConfiguration('maxRetries')),
            'fallbacks' => $this->getFallbackModels($this->extConfig['claudeFallbackModels']) ?? []
        ];
    }

    /**
     * returns the response result from the api AI provider
     *
     * @param string $prompt
     * @param array $options
     * @param int $retryCount
     * @param bool $stream
     * @return string|Generator
     */
    public function process(string $prompt, array $options = [], int &$retryCount = 0, bool $stream = false): Generator|string
    {
        $options = $this->mergeConfigRecursive($options, $this->config);

        $logOptions = $options;
        unset($logOptions['api_key']);
        $this->logger->info('Claude' . !$stream ?: 'stream' . 'info: ', ['model' => $options['model'], 'options' => $logOptions]);

        try {
            return $this->client->getContent($prompt, $options, $stream);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                if (($statusCode === 429 || $statusCode === 503) && $retryCount < $this->config['maxRetries']) {
                    $retryCount++;
                    $this->logger->warning('Claude' . $statusCode . 'error', ['model' => $options['model'], 'options' => $logOptions]);
                    $options['model'] = $this->fallbackToModel('claude', $options['model']);
                    return $this->process($prompt, $options);
                }
            }
            $this->handleServiceRequestException('Claude', $e, $logOptions, $options['model']);
            return '{error: "Claude - ' . LocalizationUtility::translate('not_available') . '"}';
        } catch (GuzzleException $e) {
            $this->handleServiceGuzzleException('Claude', $e, $logOptions, $options['model']);
            return '{error: "Claude - ' . LocalizationUtility::translate('not_available') . '"}';
        }
    }
}
