<?php

declare(strict_types=1);

defined('TYPO3') or die();

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Log\LogLevel;
use TYPO3\CMS\Core\Log\Writer\FileWriter;

(function () {
    
    $GLOBALS['TYPO3_CONF_VARS']['LOG']['W3code']['W3cAiconnector']['Service']['writerConfiguration'] = [
        // Configure for INFO level and higher
        LogLevel::INFO => [
            FileWriter::class => [
                'logFile' => Environment::getVarPath() . '/log/w3c_aiconnector_info.log',
            ],
        ]
    ];

})();
