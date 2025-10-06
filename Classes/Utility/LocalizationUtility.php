<?php
declare(strict_types=1);

namespace W3code\W3cAIConnector\Utility;

use TYPO3\CMS\Extbase\Utility\LocalizationUtility as ExtbaseLocalizationUtility;

/**
 * Class LocalizationUtility
 */
class LocalizationUtility
{
    /**
     * @param string $key
     * @param array|null $arguments
     * @return null|string
     */
    public static function translate(string $key, array|null $arguments = null): string|null
    {
        return ExtbaseLocalizationUtility::translate($key, 'w3c_aiconnector', $arguments);
    }
}
