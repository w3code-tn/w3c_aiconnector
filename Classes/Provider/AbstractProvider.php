<?php
declare(strict_types=1);

namespace W3code\W3cAIConnector\Provider;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use W3code\W3cAIConnector\DependencyInjection\ContainerAwareTrait;
use W3code\W3cAIConnector\Utility\ConfigurationUtility;

/**
 * Class AbstractProvider
 */
abstract class AbstractProvider implements ProviderInterface
{
    use ContainerAwareTrait;

    protected array $config = [];
    protected array $extConfig = [];

    protected ProviderInterface $provider;

    /**
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    public function __construct()
    {
        $this->extConfig = ConfigurationUtility::getExtensionConfiguration();
    }

    /**
     * @param array $options
     * @param array $params
     * @return array
     */
    protected function mergeConfigRecursive(array $options, array $params): array
    {
        foreach ($options as $key => $value) {
            if (array_key_exists($key, $params)) {
                $params[$key] = $value;
            } elseif (isset($params['generationConfig']) && array_key_exists($key, $params['generationConfig'])) {
                $params['generationConfig'][$key] = $value;
            }
        }
        return $params;
    }

    /**
     * @param string $provider
     * @param string $currentModel
     * @return string
     */
    protected function fallbackToModel(string $provider, string $currentModel): string
    {
        return $this->config['fallbacks'][$provider][$currentModel];
    }

    /**
     * @param string $extConfig
     * @return array
     */
    protected function getFallbackModels(string $extConfig): array
    {
        $models = array_map('trim', explode(',', $extConfig));
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
