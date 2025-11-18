<?php

declare(strict_types=1);

namespace W3code\W3cAIConnector\Utility;

use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class LogUtility
 */
class LogUtility
{
    /**
     * Logs exception details
     *
     * @param array $options
     */
    public static function logException(array $options = [], string $message = ''): void
    {
        $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);

        $logOptions = $options;

        if ($logOptions['apiKey']) {
            $logOptions['apiKey'] = ProviderUtility::maskApiKey($logOptions['apiKey']);
        }

        if(isset($options['target_lang'])) {
            $options['model'] = 'deepL';
        }

        if(isset($options['targetLang'])) {
            $options['model'] = 'googleTranslate';
        }

        $logger->error(
            $options['model'] . ' error: ',
            [
                'model' => $options['model'],
                'options' => $logOptions,
                'response' => !empty($message) ? $message : 'No response body available.',
            ]
        );
    }
}
