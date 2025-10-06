<?php

declare(strict_types=1);

namespace W3code\W3cAIConnector\Provider;

use Generator;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use W3code\W3cAIConnector\Client\MistralClient;
use W3code\W3cAIConnector\Utility\ConfigurationUtility;
use W3code\W3cAIConnector\Utility\LocalizationUtility;

class MistralProvider extends AbstractProvider
{
    use LoggerAwareTrait;

    protected ?MistralClient $client = null;

    /**
     * Set all configuration needed for the AI model provider
     *
     * @return void
     */
    public function setConfig(): void
    {
        $this->config = [
            'apiKey' => $this->extConfig['mistralApiKey'] ?? '',
            'model' => $this->extConfig['mistralModelName']
                ?? ConfigurationUtility::getDefaultConfiguration('mistralModelName'),
            'temperature' => (float)($this->extConfig['mistralTemperature']
                ?? ConfigurationUtility::getDefaultConfiguration('mistralTemperature')),
            'topP' => (float)($this->extConfig['mistralTopP']
                ?? ConfigurationUtility::getDefaultConfiguration('mistralTopP')),
            'maxTokens' => (int)($this->extConfig['mistralMaxTokens']
                ?? ConfigurationUtility::getDefaultConfiguration('mistralMaxTokens')),
            'stop' => $this->extConfig['mistralStop']
                ? GeneralUtility::trimExplode(',', $this->extConfig['mistralStop'], true)
                : ConfigurationUtility::getDefaultConfiguration('mistralStop'),
            'randomSeed' => (int)($this->extConfig['mistralRandomSeed']
                ?? ConfigurationUtility::getDefaultConfiguration('mistralRandomSeed')),
            'stream' => (bool)($this->extConfig['mistralStream']
                ?? ConfigurationUtility::getDefaultConfiguration('mistralStream')),
            'safePrompt' => (bool)($this->extConfig['mistralSafePrompt']
                ?? ConfigurationUtility::getDefaultConfiguration('mistralSafePrompt')),
            'chunkSize' => (int)($this->extConfig['mistralChunkSize']
                ?? ConfigurationUtility::getDefaultConfiguration('mistralChunkSize')),
            'maxInputTokensAllowed' => (int)($this->extConfig['mistralMaxInputTokensAllowed']
                ?? ConfigurationUtility::getDefaultConfiguration('maxInputTokensAllowed')),
            'maxRetries' => (int)($this->extConfig['maxRetries']
                ?? ConfigurationUtility::getDefaultConfiguration('maxRetries')),
            'fallbacks' => $this->getFallbackModels($this->extConfig['mistralFallbackModels']) ?? []
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
        $this->logger->info('Mistral info: ', ['model' => $options['model'], 'options' => $logOptions]);

        try {
            return $this->client->getContent($prompt, $options, $stream);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                if ($statusCode === 429 && $retryCount < $this->config['maxRetries']) {
                    $retryCount++;
                    $this->logger->warning('Mistral' . $statusCode . 'error', ['model' => $options['model'], 'options' => $logOptions]);
                    $options['model'] = $this->fallbackToModel('mistral', $options['model']);
                    return $this->process($prompt, $options);
                }
            }
            $this->handleServiceRequestException('Mistral', $e, $logOptions, $options['model']);
            return '{error: "Mistral - ' . LocalizationUtility::translate('not_available') . '"}';
        } catch (GuzzleException $e) {
            $this->handleServiceGuzzleException('Mistral', $e, $logOptions, $options['model']);
            return '{error: "Mistral - ' .  LocalizationUtility::translate('not_available') . '"}';
        }
    }
}
