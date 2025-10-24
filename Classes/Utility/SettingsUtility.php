<?php
declare(strict_types=1);

namespace W3code\W3cAIConnector\Utility;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;

/**
 * Class SettingsUtility
 */
class SettingsUtility
{
    /**
     * retrieve the extension settings
     *
     * @return array|null
     */
    public static function getSettings(?string $extensionName = '', ?string $pluginName = ''): array|null
    {
        /** @var ConfigurationManagerInterface $configurationManager */
        $configurationManager = GeneralUtility::makeInstance(ConfigurationManagerInterface::class);

        return $configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS,
            $extensionName,
            $pluginName
        );
    }
}
