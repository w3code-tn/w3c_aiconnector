<?php

declare(strict_types=1);

defined('TYPO3') or die();

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Log\LogLevel;
use TYPO3\CMS\Core\Log\Writer\FileWriter;
use W3code\W3cAIConnector\Provider\ClaudeProvider;
use W3code\W3cAIConnector\Provider\CohereProvider;
use W3code\W3cAIConnector\Provider\DeepLProvider;
use W3code\W3cAIConnector\Provider\GeminiProvider;
use W3code\W3cAIConnector\Provider\GoogleTranslateProvider;
use W3code\W3cAIConnector\Provider\MistralProvider;
use W3code\W3cAIConnector\Provider\OllamaProvider;
use W3code\W3cAIConnector\Provider\OpenAIProvider;
use W3code\W3cAIConnector\Service\SolrService;

(function () {
    $extensionName = 'w3c_aiconnector';

    // AI providers list
    $GLOBALS['TYPO3_CONF_VARS']['EXT'][$extensionName]['gemini'] = GeminiProvider::class;
    $GLOBALS['TYPO3_CONF_VARS']['EXT'][$extensionName]['claude'] = ClaudeProvider::class;
    $GLOBALS['TYPO3_CONF_VARS']['EXT'][$extensionName]['cohere'] = CohereProvider::class;
    $GLOBALS['TYPO3_CONF_VARS']['EXT'][$extensionName]['deepl'] = DeepLProvider::class;
    $GLOBALS['TYPO3_CONF_VARS']['EXT'][$extensionName]['googleTranslate'] = GoogleTranslateProvider::class;
    $GLOBALS['TYPO3_CONF_VARS']['EXT'][$extensionName]['mistral'] = MistralProvider::class;
    $GLOBALS['TYPO3_CONF_VARS']['EXT'][$extensionName]['openai'] = OpenAIProvider::class;
    $GLOBALS['TYPO3_CONF_VARS']['EXT'][$extensionName]['ollama'] = OllamaProvider::class;

    // AI Services list
    $GLOBALS['TYPO3_CONF_VARS']['EXT'][$extensionName]['solr'] = SolrService::class;

    $GLOBALS['TYPO3_CONF_VARS']['LOG']['W3code']['W3cAIConnector']['Provider']['writerConfiguration'] = [
        // Configure for INFO level and higher
        LogLevel::INFO => [
            FileWriter::class => [
                'logFile' => Environment::getVarPath() . '/log/w3c_aiconnector_info.log',
            ],
        ]
    ];

})();
