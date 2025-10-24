<?php

declare(strict_types=1);

namespace W3code\W3cAIConnector\Factory;

use Exception;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use W3code\W3cAIConnector\Provider\ProviderInterface;
use W3code\W3cAIConnector\Utility\ConfigurationUtility;

/**
 * Class ProviderFactory
 */
class ProviderFactory
{
    /**
     * initialize the AI model provider instance.
     *
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws Exception
     *
     * @return ProviderInterface
     */
    public function create(?string $providerName = ''): ProviderInterface
    {
        if(!empty($providerName)) {
            $provider = $providerName;
        } else {
            $provider = $this->getProvider();
        }

        return $this->getProviderInstance($provider);
    }

    /**
     * return the AI model provider name from the extension configuration, e.x: gemini
     *
     * @return string
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    public function getProvider(): string
    {
        $extConfig = ConfigurationUtility::getExtensionConfiguration('w3c_aiconnector');

        return $extConfig['provider'];
    }

    /**
     * returns the singleton of a provider identifier
     *
     * @param $providerIdentifier
     * @return ProviderInterface
     * @throws Exception
     */
    public function getProviderInstance($providerIdentifier): ProviderInterface
    {
        $className = $GLOBALS['TYPO3_CONF_VARS']['EXT']['w3c_aiconnector'][$providerIdentifier];

        if (empty($className)) {
            throw new Exception(
                sprintf('No provider instance found for the identifier "%s"', $providerIdentifier),
                1504017523
            );
        }

        return GeneralUtility::makeInstance($className);
    }
}
