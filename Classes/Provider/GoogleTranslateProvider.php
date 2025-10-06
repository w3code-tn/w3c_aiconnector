<?php

declare(strict_types=1);

namespace W3code\W3cAIConnector\Provider;

use Generator;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerAwareTrait;
use W3code\W3cAIConnector\Client\GoogleTranslateClient;
use W3code\W3cAIConnector\Utility\ConfigurationUtility;
use W3code\W3cAIConnector\Utility\LocalizationUtility;

class GoogleTranslateProvider extends AbstractProvider
{
    use LoggerAwareTrait;

    protected ?GoogleTranslateClient $client = null;

    /**
     * Set all configuration needed for the AI model provider
     *
     * @return void
     */
    public function setConfig(): void
    {
        $this->config = [
            'apiKey' => $this->extConfig['googleTranslateApiKey'] ?? '',
            'targetLang' => $this->extConfig['googleTranslateTargetLang']
                ?? ConfigurationUtility::getDefaultConfiguration('googleTranslateTargetLang'),
            'sourceLang' => $this->extConfig['googleTranslateSourceLang']
                ?? ConfigurationUtility::getDefaultConfiguration('googleTranslateSourceLang'),
            'format' => $this->extConfig['googleTranslateFormat']
                ?? ConfigurationUtility::getDefaultConfiguration('googleTranslateFormat'),
            'model' => $this->extConfig['googleTranslateModel']
                ?? ConfigurationUtility::getDefaultConfiguration('googleTranslateModel'),
            'cid' => $this->extConfig['googleTranslateCid']
                ?? ConfigurationUtility::getDefaultConfiguration('googleTranslateCid'),
            'maxRetries' => (int)($this->extConfig['maxRetries']
                ?? ConfigurationUtility::getDefaultConfiguration('maxRetries')),
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
        $this->logger->info('Google Translate info:', ['targetLang' => $options['targetLang'], 'options' => $logOptions]);

        try {
            $result = $this->client->getContent($prompt, $options, $stream);

            if($stream) {
                return $result;
            } else {
                if ($result === null) {
                    yield '';
                    return null;
                }

                if (str_starts_with($result, '{error:')) {
                    yield $result;
                    return null;
                }

                // Découpe le texte en phrases ou en morceaux de 50 caractères
                $chunkSize = 50;
                $length = strlen($result);
                for ($i = 0; $i < $length; $i += $chunkSize) {
                    yield mb_substr($result, $i, $chunkSize);
                    // Optionnel: flush pour forcer l’envoi
                    if (ob_get_level() > 0) ob_flush();
                    flush();
                }
            }
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                if (($statusCode === 429 || $statusCode === 456) && $retryCount < $this->config['maxRetries']) {
                    $retryCount++;
                    $this->logger->warning('Google Translate' . $statusCode . 'error', ['options' => $logOptions]);
                    sleep(5); // Wait 5 seconds before retrying
                    return $this->process($prompt, $options);
                }
            }
            $this->handleServiceRequestException('Google Translate', $e, $logOptions, $options['model']);
            return '{error: "Google Translate - ' . LocalizationUtility::translate('not_available') . '"}';
        } catch (GuzzleException $e) {
            $this->handleServiceGuzzleException('Google Translate', $e, $logOptions, $options['model']);
            return '{error: "Google Translate - ' . LocalizationUtility::translate('not_available') . '"}';
        }
    }
}
