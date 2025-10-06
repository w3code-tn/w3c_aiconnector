<?php

declare(strict_types=1);

namespace W3code\W3cAIConnector\Provider;

use Generator;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use W3code\W3cAIConnector\Client\OpenAIClient;
use W3code\W3cAIConnector\Utility\ConfigurationUtility;
use W3code\W3cAIConnector\Utility\LocalizationUtility;

class OpenAIProvider extends AbstractProvider
{

    use LoggerAwareTrait;

    protected ?OpenAIClient $client = null;

    /**
     * Set all configuration needed for the AI model provider
     *
     * @return void
     */
    public function setConfig(): void
    {
        $this->config = [
            'apiKey' => $this->extConfig['openAiApiKey'] ?? '',
            'model' => $this->extConfig['openAiModelName']
                ?? ConfigurationUtility::getDefaultConfiguration('openaiModelName'),
            'temperature' => (float)($this->extConfig['openAiTemperature']
                ?? ConfigurationUtility::getDefaultConfiguration('openAiTemperature')),
            'topP' => (float)($this->extConfig['openAiTopP']
                ?? ConfigurationUtility::getDefaultConfiguration('openAiTopP')),
            'max_output_tokens' => (int)($this->extConfig['openAiMaxOutputTokens']
                ?? ConfigurationUtility::getDefaultConfiguration('openAiMaxOutputTokens')),
            'stop' => $this->extConfig['openAiStop']
                ? GeneralUtility::trimExplode(',', $this->extConfig['openAiStop'], true)
                : ConfigurationUtility::getDefaultConfiguration('openAiStop'),
            'stream' => (bool)($this->extConfig['openAiStream']
                ?? ConfigurationUtility::getDefaultConfiguration('openAiStream')),
            'chunkSize' => (int)($this->extConfig['openAiChunkSize']
                ?? ConfigurationUtility::getDefaultConfiguration('openAiChunkSize')),
            'maxInputTokensAllowed' => (int)($this->extConfig['openAiMaxInputTokensAllowed']
                ?? ConfigurationUtility::getDefaultConfiguration('maxInputTokensAllowed')),
            'maxRetries' => (int)($this->extConfig['maxRetries']
                ?? ConfigurationUtility::getDefaultConfiguration('maxRetries')),
            'fallbacks' => $this->getFallbackModels($this->extConfig['ollamaFallbackModels']) ?? []
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
        $this->logger->info('OpenAI info: ', ['model' => $options['model'], 'options' => $logOptions]);

        try {
            return $this->client->getContent($prompt, $options, $stream);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                if ($statusCode === 429 && $retryCount < $this->config['maxRetries']) {
                    $retryCount++;
                    $this->logger->warning('OpenAI' . $statusCode . 'error', ['model' => $options['model'], 'options' => $logOptions]);
                    $options['model'] = $this->fallbackToModel('openai', $options['model']);
                    return $this->process($prompt, $options);
                }
            }
            $this->handleServiceRequestException('OpenAI', $e, $logOptions, $options['model']);
            return '{error: "OpenAI - ' . LocalizationUtility::translate('not_available') . '"}';
        }
        catch (GuzzleException $e) {
            $this->handleServiceGuzzleException('OpenAI', $e, $logOptions, $options['model']);
            return '{error: "OpenAI - ' .  LocalizationUtility::translate('not_available') . '"}';
        }
    }
}
