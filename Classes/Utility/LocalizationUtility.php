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
     * @return string|null
     */
    public static function translate(string $key, string $extensionName, ?array $arguments = null): ?string
    {
        return ExtbaseLocalizationUtility::translate($key, $extensionName, $arguments);
    }
}
