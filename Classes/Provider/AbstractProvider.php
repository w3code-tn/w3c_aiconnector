<?php
declare(strict_types=1);

namespace W3code\W3cAIConnector\Provider;

use Generator;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use W3code\W3cAIConnector\DependencyInjection\ContainerAwareTrait;
use W3code\W3cAIConnector\Utility\ConfigurationUtility;
use W3code\W3cAIConnector\Utility\ProviderUtility;

/**
 * Class AbstractProvider
 */
abstract class AbstractProvider implements ProviderInterface, LoggerAwareInterface
{
    use ContainerAwareTrait;
    use LoggerAwareTrait;

    protected array $config = [];
    protected array $extConfig = [];

    /**
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    public function __construct()
    {
        $this->extConfig = ConfigurationUtility::getExtensionConfiguration();
    }

    /**
     * returns the response result from the api AI provider
     * base implementation to be overridden by child classes
     *
     * @param string $prompt
     * @param array $options
     * @param int $retryCount
     * @param bool $stream
     *
     * @return string|Generator
     */
    public function process(string $prompt, array $options = [], int &$retryCount = 0, bool $stream = false): Generator|string
    {
        $options = array_replace_recursive($options, $this->config);

        $logOptions = $options;
        $logOptions['apiKey'] = ProviderUtility::maskApiKey($logOptions['apiKey']);

        // base implementation does nothing
        return '';
    }

    /**
     * fallback to another model if configured
     *
     * @param string $provider
     * @param string $currentModel
     * @return string
     */
    protected function fallbackToModel(string $provider, string $currentModel): string
    {
        return $this->config['fallbacks'][$provider][$currentModel];
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
     * @todo : create a custom Exception Class
     *
     * @param string $provider
     * @param RequestException $e
     * @param array $logOptions
     * @param string|null $model
     * @return void
     */
    protected function handleServiceRequestException(
        string $provider,
        RequestException $e,
        array $logOptions,
        ?string $model
    ): void {
        if ($e->hasResponse()) {
            $responseBody = $e->getResponse()->getBody()->getContents();
            // Attempt to decode as JSON
            $jsonError = json_decode($responseBody, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $responseBody = $jsonError; // Use decoded JSON
            }
        }

        $this->logger->error(
            $provider . ' error: ',
            [
                'model' => $model,
                'options' => $logOptions,
                'response' => $responseBody ?? 'No response body available.'
            ]
        );
    }

    /**
     * @todo : create a custom Exception Class
     *
     * @param string $provider
     * @param GuzzleException $e
     * @param array $logOptions
     * @param string|null $model
     * @return void
     */
    protected function handleServiceGuzzleException(
        string $provider,
        GuzzleException $e,
        array $logOptions,
        ?string $model,
    ): void {
        $this->logger->error(
            $provider . ' error: ',
            [
                'model' => $model,
                'options' => $logOptions,
                'response' => 'No response body available.'
            ]
        );
    }
}
