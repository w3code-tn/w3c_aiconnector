<?php

declare(strict_types=1);

namespace W3code\W3cAIConnector\Provider;

use Generator;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use W3code\W3cAIConnector\Client\ClaudeClient;
use W3code\W3cAIConnector\Utility\LocalizationUtility;

/**
 * Class ClaudeProvider
 */
class ClaudeProvider extends AbstractProvider
{

    protected ?ClaudeClient $client = null;

    /**
     * Set all configuration needed for the AI model provider
     *
     * @return void
     */
    public function setup(): void
    {
        $this->config = [
            'apiKey' => $this->extConfig['claudeApiKey'],
            'model' => $this->extConfig['claudeModelName'],
            'apiVersion' => $this->extConfig['claudeApiVersion'],
            'maxTokens' => (int)$this->extConfig['claudeMaxTokens'],
            'system' => $this->extConfig['claudeSystem'],
            'stopSequences' => explode(',', $this->extConfig['claudeStopSequences']),
            'stream' => (bool)$this->extConfig['claudeStream'],
            'temperature' => (float)$this->extConfig['claudeTemperature'],
            'topP' => (float)$this->extConfig['claudeTopP'],
            'topK' => (int)$this->extConfig['claudeTopK'],
            'chunkSize' => (int)$this->extConfig['claudeChunkSize'],
            'maxInputTokensAllowed' => (int)$this->extConfig['claudeMaxInputTokensAllowed'],
            'maxRetries' => (int)$this->extConfig['maxRetries'],
            'fallbacks' => $this->getFallbackModels($this->extConfig['claudeFallbackModels'])
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
    public function process(string $prompt, array $options = [], int &$retryCount = 0, bool $stream = false): string|Generator
    {
        parent::process($prompt, $options, $retryCount, $stream);

        $logOptions = $options;
        $this->logger->info(
            ucfirst($options['model']) . ($stream ? ' stream ' : ' ') . 'info: ',
            ['model' => $options['model'], 'options' => $logOptions]
        );

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
