<?php

declare(strict_types=1);

namespace W3code\W3cAIConnector\Provider;

use Generator;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerAwareTrait;
use W3code\W3cAIConnector\Client\GeminiClient;
use W3code\W3cAIConnector\Utility\ConfigurationUtility;
use W3code\W3cAIConnector\Utility\LocalizationUtility;

/**
 * Class GeminiProvider
 */
class GeminiProvider extends AbstractProvider
{
    use LoggerAwareTrait;

    protected ?GeminiClient $client = null;

    /**
     * Set all configuration needed for the AI model provider
     *
     * @return void
     */
    public function setConfig(): void
    {
        $this->config = [
            'apiKey' => $this->extConfig['geminiApiKey'] ?? '',
            'model' => $this->extConfig['geminiModelName'] ?? ConfigurationUtility::getDefaultConfiguration('geminiModelName'),
            'generationConfig' => [
                'temperature' => (float)($this->extConfig['geminiTemperature']
                    ?? ConfigurationUtility::getDefaultConfiguration('geminiTemperature')),
                'topP' => (float)($this->extConfig['geminiTopP']
                    ?? ConfigurationUtility::getDefaultConfiguration('geminiTopP')),
                'topK' => (int)($this->extConfig['geminiTopK']
                    ?? ConfigurationUtility::getDefaultConfiguration('geminiTopK')),
                'candidateCount' => (int)($this->extConfig['geminiCandidateCount']
                    ?? ConfigurationUtility::getDefaultConfiguration('geminiCandidateCount')),
                'maxOutputTokens' => (int)($this->extConfig['geminiMaxOutputTokens']
                    ?? ConfigurationUtility::getDefaultConfiguration('geminiMaxOutputTokens')),
                'stopSequences' => $this->extConfig['geminiStopSequences']
                    ? explode(',', $this->extConfig['geminiStopSequences'])
                    : ConfigurationUtility::getDefaultConfiguration('geminiStopSequences'),
            ],
            'chunkSize' => (int)($this->extConfig['geminiChunkSize']
                ?? ConfigurationUtility::getDefaultConfiguration('streamChunkSize')),
            'maxInputTokensAllowed' => (int)($this->extConfig['geminiMaxInputTokensAllowed']
                ?? ConfigurationUtility::getDefaultConfiguration('maxInputTokensAllowed')),
            'maxRetries' => (int)($this->extConfig['maxRetries']
                ?? ConfigurationUtility::getDefaultConfiguration('maxRetries')),
            'fallbacks' => $this->getFallbackModels($this->extConfig['geminiFallbackModels']) ?? []
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
        $this->logger->info('Gemini' . !$stream ?: 'stream' . 'info: ', ['model' => $options['model'], 'options' => $logOptions]);

        try {
            return $this->client->getContent($prompt, $options, $stream);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                if (($statusCode === 429 || $statusCode === 503) && $retryCount < $this->config['maxRetries']) {
                    $retryCount++;
                    $this->logger->warning('Gemini' . $statusCode . 'error', ['model' => $options['model'], 'options' => $logOptions]);
                    $options['model'] = $this->fallbackToModel('gemini', $options['model']);
                    return $this->process($prompt, $options);
                }
            }
            $this->handleServiceRequestException('Gemini', $e, $logOptions, $options['model']);
            return '{error: "Gemini - ' . LocalizationUtility::translate('not_available') . '"}';
        } catch (GuzzleException $e) {
            $this->handleServiceGuzzleException('Gemini', $e, $logOptions, $options['model']);
            return '{error: "Gemini - ' .  LocalizationUtility::translate('not_available') . '"}';
        }
    }
}
