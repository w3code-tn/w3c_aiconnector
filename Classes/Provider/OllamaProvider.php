<?php

declare(strict_types=1);

namespace W3code\W3cAIConnector\Provider;

use Generator;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use W3code\W3cAIConnector\Client\OllamaClient;
use W3code\W3cAIConnector\Utility\ConfigurationUtility;
use W3code\W3cAIConnector\Utility\LocalizationUtility;

class OllamaProvider extends AbstractProvider
{

    protected ?OllamaClient $client = null;

    /**
     * Set all configuration needed for the AI model provider
     *
     * @return void
     */
    public function setup(): void
    {
        $this->config = [
            'model' => $this->extConfig['ollamaModelName'],
            'endPoint' => $this->extConfig['ollamaEndPoint'],
            'stream' => (bool)$this->extConfig['ollamaStream'],
            'temperature' => (float)$this->extConfig['ollamaTemperature'],
            'topP' => (float)$this->extConfig['ollamaTopP'],
            'numPredict' => (int)$this->extConfig['ollamaNumPredict'],
            'stop' => GeneralUtility::trimExplode(',', $this->extConfig['ollamaStop'], true),
            'format' => $this->extConfig['ollamaFormat'],
            'system' => $this->extConfig['ollamaSystem'],
            'chunkSize' => (int)$this->extConfig['ollamaChunkSize'],
            'maxInputTokensAllowed' => (int)$this->extConfig['ollamaMaxInputTokensAllowed'],
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
        $this->logger->info('Ollama info: ', ['model' => $options['model'], 'options' => $logOptions]);

        try {
            return $this->client->getContent($prompt, $options, $stream);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                if (($statusCode === 429 || $statusCode === 503) && $retryCount < $this->config['maxRetries']) {
                    $retryCount++;
                    $this->logger->warning('Ollama' . $statusCode . 'error', ['model' => $options['model'], 'options' => $logOptions]);
                    // $options['model'] = $this->fallbackToModel('ollama', $options['model']);
                    return $this->process($prompt, $options);
                }
            }
            $this->handleServiceRequestException('Ollama', $e, $logOptions, $options['model']);
            return '{error: "Ollama - ' . LocalizationUtility::translate('not_available') . '"}';
        } catch (GuzzleException $e) {
            $this->handleServiceGuzzleException('Ollama', $e, $logOptions, $options['model']);
            return '{error: "Ollama - ' .  LocalizationUtility::translate('not_available') . '"}';
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
