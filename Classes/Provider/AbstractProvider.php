<?php

declare(strict_types=1);

namespace W3code\W3cAIConnector\Provider;

use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use W3code\W3cAIConnector\Utility\ConfigurationUtility;
use W3code\W3cAIConnector\Utility\LogUtility;
use W3code\W3cAIConnector\Utility\ProviderUtility;

/**
 * Class AbstractProvider
 */
abstract class AbstractProvider implements ProviderInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public array $config = [];
    protected array $extConfig = [];
    protected int $retryCount = 0;

    /**
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    public function __construct()
    {
        $this->extConfig = ConfigurationUtility::getExtensionConfiguration('w3c_aiconnector');
    }

    /**
     * handles the processing of the AI request
     *
     * @param callable $requestCallback
     * @param string $prompt
     * @param string $providerName
     * @param array $options
     * @param bool $stream
     */
    protected function handleProcess(callable $requestCallback, string $prompt, string $providerName, array $options = [], bool $stream = false)
    {
        $options = array_replace_recursive($options, $this->config);

        $logOptions = $options;
        $logOptions['apiKey'] = ProviderUtility::maskApiKey($logOptions['apiKey']);

        $this->logger->info(
            ucfirst($providerName) . ($stream ? ' stream info: ' : ' info: '),
            $this->setLogContext($options, $logOptions) ?? []
        );

        try {
            return $requestCallback($prompt, $options);
        } catch (RequestException $e) {
            return $this->handleProcessException($e, $prompt, $providerName, $options, $logOptions, $stream);
        }
    }

    /**
     * handles exceptions during AI request processing
     *
     * @param RequestException $e
     * @param string $prompt
     * @param array $options
     * @param array $logOptions
     * @param bool $stream
     *
     */
    private function handleProcessException(RequestException $e, string $prompt, string $providerName, array $options, array $logOptions, bool $stream)
    {
        if ($e->hasResponse()) {
            $statusCode = $e->getResponse()->getStatusCode();

            if ($statusCode && $this->retryCount < $this->config['maxRetries']) {
                $this->logger->warning(ucfirst($providerName) . $statusCode . ' error', [
                    'model' => $options['model'],
                    'options' => $logOptions,
                ]);
                $options['model'] = $this->fallbackToModel($options['model']);
                $this->retryCount++;
                if($stream) {
                    yield from $this->processStream($prompt, $options);
                    return;
                } else {
                    return $this->process($prompt, $options);
                }
            }
        }

        if ($e->hasResponse()) {
            $responseBody = $e->getResponse()->getBody()->getContents();
            // Attempt to decode as JSON
            $jsonError = json_decode($responseBody, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $responseBody = $jsonError; // Use decoded JSON
            }
        }

        LogUtility::logException($options, $responseBody);
    }

    /**
     * fallback to another model if configured
     *
     * @return array
     */
    protected function fallbackToModel(string $model): string
    {
        return $this->config['fallbacks'][$model];
    }

    /**
     * parse the fallback models configuration
     *
     * @param string $configuration
     * @return array
     */
    protected function getFallbackModels(string $configuration): array
    {
        $models = array_map('trim', explode(',', $configuration));

        $fallbacks = [];
        if (count($models) > 1) {
            for ($i = 0; $i < count($models); $i++) {
                $fallbacks[$models[$i]] = $models[($i + 1) % count($models)];
            }
        }
        return $fallbacks;
    }

    /**
     * sets the log context
     */
    protected function setLogContext(array $options, array $logOptions): ?array
    {
        return [
            'model' => $options['model'],
            'options' => $logOptions
        ];
    }
}
