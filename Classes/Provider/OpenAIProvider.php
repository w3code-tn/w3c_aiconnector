<?php

declare(strict_types=1);

namespace W3code\W3cAIConnector\Provider;

use Generator;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use W3code\W3cAIConnector\Client\OpenAIClient;
use W3code\W3cAIConnector\Utility\ConfigurationUtility;
use W3code\W3cAIConnector\Utility\LocalizationUtility;

class OpenAIProvider extends AbstractProvider
{

    protected ?OpenAIClient $client = null;

    /**
     * Set all configuration needed for the AI model provider
     *
     * @return void
     */
    public function setup(): void
    {
        $this->config = [
            'apiKey' => $this->extConfig['openAiApiKey'],
            'model' => $this->extConfig['openAiModelName'],
            'temperature' => (float)$this->extConfig['openAiTemperature'],
            'topP' => (float)$this->extConfig['openAiTopP'],
            'max_output_tokens' => (int)$this->extConfig['openAiMaxOutputTokens'],
            'stop' => GeneralUtility::trimExplode(',', $this->extConfig['openAiStop'], true),
            'stream' => (bool)$this->extConfig['openAiStream'],
            'chunkSize' => (int)$this->extConfig['openAiChunkSize'],
            'maxInputTokensAllowed' => (int)$this->extConfig['openAiMaxInputTokensAllowed'],
            'maxRetries' => (int)$this->extConfig['maxRetries'],
            'fallbacks' => $this->getFallbackModels($this->extConfig['ollamaFallbackModels'])
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
        parent::process($prompt, $options, $retryCount, $stream);

        $logOptions = $options;
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
