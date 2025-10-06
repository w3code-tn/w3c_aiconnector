<?php

declare(strict_types=1);

namespace W3code\W3cAIConnector\Provider;

use Generator;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Log\LogManagerInterface;
use W3code\W3cAIConnector\Client\CohereClient;
use W3code\W3cAIConnector\Provider\ProviderInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use W3code\W3cAIConnector\Utility\ConfigurationUtility;
use W3code\W3cAIConnector\Utility\LocalizationUtility;

class CohereProvider extends AbstractProvider
{
    use LoggerAwareTrait;

    protected ?CohereClient $client = null;

    /**
     * Set all configuration needed for the AI model provider
     *
     * @return void
     */
    public function setConfig(): void
    {
        $this->config = [
            'apiKey' => $this->extConfig['cohereApiKey'] ?? '',
            'model' => $this->extConfig['cohereModelName']
                ?? ConfigurationUtility::getDefaultConfiguration('cohereModelName'),
            'maxTokens' => (int)($this->extConfig['cohereMaxTokens']
                ?? ConfigurationUtility::getDefaultConfiguration('cohereMaxTokens')),
            'temperature' => (float)($this->extConfig['cohereTemperature']
                ?? ConfigurationUtility::getDefaultConfiguration('cohereTemperature')),
            'p' => (float)($this->extConfig['cohereP']
                ?? ConfigurationUtility::getDefaultConfiguration('cohereP')),
            'k' => (int)($this->extConfig['cohereK']
                ?? ConfigurationUtility::getDefaultConfiguration('cohereK')),
            'frequencyPenalty' => (float)($this->extConfig['cohereFrequencyPenalty']
                ?? ConfigurationUtility::getDefaultConfiguration('cohereFrequencyPenalty')),
            'presencePenalty' => (float)($this->extConfig['coherePresencePenalty']
                ?? ConfigurationUtility::getDefaultConfiguration('coherePresencePenalty')),
            'stopSequences' => $this->extConfig['cohereStopSequences']
                ? explode(',', $this->extConfig['cohereStopSequences'])
                : ConfigurationUtility::getDefaultConfiguration('cohereStopSequences'),
            'stream' => (bool)($this->extConfig['cohereStream']
                ?? ConfigurationUtility::getDefaultConfiguration('cohereStream')),
            'preamble' => $this->extConfig['claudeChunkSize']
                ?? ConfigurationUtility::getDefaultConfiguration('claudeChunkSize'),
            'chatHistory' => [], // Cohere expects an array of messages for chat_history
            'chunkSize' => (int)($this->extConfig['claudeMaxInputTokensAllowed']
                ?? ConfigurationUtility::getDefaultConfiguration('maxInputTokensAllowed')),
            'maxInputTokensAllowed' => (int)($this->extConfig['claudeMaxInputTokensAllowed']
                ?? ConfigurationUtility::getDefaultConfiguration('maxInputTokensAllowed')),
            'maxRetries' => (int)($this->extConfig['maxRetries']
                ?? ConfigurationUtility::getDefaultConfiguration('maxRetries')),
            'fallbacks' => $this->getFallbackModels($this->extConfig['cohereFallbackModels']) ?? [] // to verify
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
        $this->logger->info('Cohere' . !$stream ?: 'stream' . 'info: ', ['model' => $options['model'], 'options' => $logOptions]);

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
}
