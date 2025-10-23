<?php
declare(strict_types=1);

namespace W3code\W3cAIConnector\Factory;

use Exception;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use W3code\W3cAIConnector\Service\ServiceInterface;
use W3code\W3cAIConnector\Utility\ConfigurationUtility;


/**
 * Class ServiceFactory
 */
class ServiceFactory
{

    /**
     * return the AI model provider name, e.x: gemini
     * NOTE: override this function in any other extension to add your custom provider
     *
     * @return string
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    public function getService(): string
    {
        $extConfig = ConfigurationUtility::getExtensionConfiguration();

        return $extConfig['indexEngine'];
    }

    /**
     * initialize the Ai model provider instance.
     * @return ServiceInterface
     *
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws Exception
     */
    public function setService(): ServiceInterface
    {
        return $this->getServiceInstance($this->getService());
    }

    /**
     * returns the singleton of a service identifier
     *
     * @param $serviceIdentifier
     * @return ServiceInterface
     * @throws Exception
     */
    public function getServiceInstance($serviceIdentifier): ServiceInterface
    {
        $className = $GLOBALS['TYPO3_CONF_VARS']['EXT']['w3c_aiconnector'][$serviceIdentifier];

        if (empty($className)) {
            throw new Exception(
                sprintf('No service instance found for the identifier "%s"', $serviceIdentifier),
                1504017523
            );
        }

        return GeneralUtility::makeInstance($className);
    }
}
