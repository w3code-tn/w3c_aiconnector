<?php

declare(strict_types=1);

namespace W3code\W3cAIConnector\Provider;

use Generator;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use W3code\W3cAIConnector\Client\CohereClient;
use W3code\W3cAIConnector\Utility\ConfigurationUtility;
use W3code\W3cAIConnector\Utility\LocalizationUtility;
use W3code\W3cAIConnector\Utility\ProviderUtility;

class CohereProvider extends AbstractProvider
{
    protected ?CohereClient $client = null;

    /**
     * Set all configuration needed for the AI model provider
     *
     * @return void
     */
    public function setup(): void
    {
        $this->config = [
            'apiKey' => $this->extConfig['cohereApiKey'],
            'model' => $this->extConfig['cohereModelName'],
            'maxTokens' => (int)$this->extConfig['cohereMaxTokens'],
            'temperature' => (float)$this->extConfig['cohereTemperature'],
            'p' => (float)$this->extConfig['cohereP'],
            'k' => (int)$this->extConfig['cohereK'],
            'frequencyPenalty' => (float)$this->extConfig['cohereFrequencyPenalty'],
            'presencePenalty' => (float)$this->extConfig['coherePresencePenalty'],
            'stopSequences' => explode(',', $this->extConfig['cohereStopSequences']),
            'stream' => (bool)$this->extConfig['cohereStream'],
            'preamble' => $this->extConfig['claudeChunkSize'],
            'chatHistory' => [], // Cohere expects an array of messages for chat_history
            'chunkSize' => (int)$this->extConfig['claudeMaxInputTokensAllowed'],
            'maxInputTokensAllowed' => (int)$this->extConfig['claudeMaxInputTokensAllowed'],
            'maxRetries' => (int)$this->extConfig['maxRetries'],
            'fallbacks' => $this->getFallbackModels($this->extConfig['cohereFallbackModels'])
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
                if ($statusCode === 429 && $retryCount < $this->config['maxRetries']) {
                    $retryCount++;
                    $this->logger->warning('Cohere' . $statusCode . 'error', ['model' => $options['model'], 'options' => $logOptions]);
                    $options['model'] = $this->fallbackToModel('cohere', $options['model']);
                    return $this->process($prompt, $options);
                }
            }
            $this->handleServiceRequestException('Cohere', $e, $logOptions, $options['model']);
            return '{error: "Cohere - ' . LocalizationUtility::translate('not_available') . '"}';
        } catch (GuzzleException $e) {
            $this->handleServiceGuzzleException('Cohere', $e, $logOptions, $options['model']);
            return '{error: "Cohere - ' . LocalizationUtility::translate('not_available') . '"}';
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
