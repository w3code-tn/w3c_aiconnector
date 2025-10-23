<?php

declare(strict_types=1);

namespace W3code\W3cAIConnector\Provider;

use Generator;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use W3code\W3cAIConnector\Client\MistralClient;
use W3code\W3cAIConnector\Utility\ConfigurationUtility;
use W3code\W3cAIConnector\Utility\LocalizationUtility;

class MistralProvider extends AbstractProvider
{

    protected ?MistralClient $client = null;

    /**
     * Set all configuration needed for the AI model provider
     *
     * @return void
     */
    public function setup(): void
    {
        $this->config = [
            'apiKey' => $this->extConfig['mistralApiKey'],
            'model' => $this->extConfig['mistralModelName'],
            'temperature' => (float)$this->extConfig['mistralTemperature'],
            'topP' => (float)$this->extConfig['mistralTopP'],
            'maxTokens' => (int)$this->extConfig['mistralMaxTokens'],
            'stop' => GeneralUtility::trimExplode(',', $this->extConfig['mistralStop'], true),
            'randomSeed' => (int)$this->extConfig['mistralRandomSeed'],
            'stream' => (bool)$this->extConfig['mistralStream'],
            'safePrompt' => (bool)$this->extConfig['mistralSafePrompt'],
            'chunkSize' => (int)$this->extConfig['mistralChunkSize'],
            'maxInputTokensAllowed' => (int)$this->extConfig['mistralMaxInputTokensAllowed'],
            'maxRetries' => (int)$this->extConfig['maxRetries'],
            'fallbacks' => $this->getFallbackModels($this->extConfig['mistralFallbackModels'])
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
