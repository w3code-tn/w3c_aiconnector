<?php
declare(strict_types=1);

namespace W3code\W3cAIConnector\Utility;

use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\Exception\MissingArrayPathException;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class ConfigurationUtility
 */
class ConfigurationUtility
{
    final public const DEFAULT_CONFIGURATION = [
        'claudeModelName' => 'claude-opus-4-1-20250805',
        'cohereModelName' => 'command-a-03-2025',
        'cohereMaxTokens' => 1024,
        'cohereTemperature' => 0.3,
        'cohereP' => 0.75,
        'cohereK' => 0,
        'cohereFrequencyPenalty' => 0.0,
        'coherePresencePenalty' => 0.0,
        'cohereStopSequences' => [],
        'cohereStream' => false,
        'coherePreamble' => '',
        'geminiModelName' => 'gemini-2.5-flash',
        'mistralModelName' => 'mistral-large-latest',
        'mistralTemperature' => 0.7,
        'mistralTopP' => 1.0,
        'mistralMaxTokens' => 1024,
        'mistralStop' => [],
        'mistralRandomSeed' => 0,
        'mistralStream' => false,
        'mistralSafePrompt' => false,
        'ollamaApiEndpoint' => 'http://ollama:11434',
        'ollamaTemperature' => 0.8,
        'ollamaTopP' => 0.9,
        'ollamaNumPredict' => 1024,
        'ollamaStop' => [],
        'ollamaFormat' => '',
        'ollamaSystem' => '',
        'ollamaModel' => 'llama3',
        'openaiTemperature' => 1.0,
        'openaiTopP' => 1.0,
        'openaiMaxTokens' => 1024,
        'openaiStop' => [],
        'openaiStream' => false,
        'openaiPresencePenalty' => 0.0,
        'openaiFrequencyPenalty' => 0.0,
        'openaiModelName' => 'gpt-4o',
        'claudeApiVersion' => '2023-06-01',
        'claudeMaxTokens' => 1024,
        'claudeSystem' => '',
        'claudeStopSequences' => [],
        'claudeStream' => false,
        'claudeTemperature' => 1.0,
        'claudeTopP' => 1.0,
        'claudeTopK' => 5,
        'deeplTargetLang' => 'EN-US',
        'deeplSourceLang' => '',
        'deeplSplitSentences' => 'on',
        'deeplPreserveFormatting' => false,
        'deeplFormality' => 'default',
        'deeplGlossaryId' => '',
        'deeplTagHandling' => '',
        'deeplOutlineDetection' => false,
        'deeplNonSplittingTags' => '',
        'googleTranslateTargetLang' => 'en',
        'googleTranslateSourceLang' => '',
        'googleTranslateFormat' => 'html',
        'googleTranslateModel' => '',
        'googleTranslateCid' => '',
        'ollamaStream' => false,
        'geminiStream' => false,
        'geminiTemperature' => 0.9,
        'geminiTopP' => 0.95,
        'geminiTopK' => 40,
        'geminiCandidateCount' => 1,
        'geminiMaxOutputTokens' => 1024,
        'geminiStopSequences' => [],
        'streamChunkSize' => 50,
        'maxInputTokensAllowed' => 1000000,
        'maxRetries' => 5,
        'fallbacks' => [
            'gemini' => [
                'gemini-2.5-flash' => 'gemini-2.0-flash',
                'gemini-2.0-flash' => 'gemini-1.5-flash',
                'gemini-1.5-flash' => 'gemini-2.5-flash',
            ],
            'openai' => [
                'gpt-4o' => 'gpt-4o-mini',
                'gpt-4o-mini' => 'gpt-3.5-turbo',
                'gpt-3.5-turbo' => 'gpt-4o',
            ],
            'claude' => [
                'claude-3-opus-20240229' => 'claude-2',
                'claude-2' => 'claude-instant-100k',
                'claude-instant-100k' => 'claude-3-opus-20240229',
            ],
            'cohere' => [
                'command-r-plus' => 'command-xlarge-nightly',
                'command-xlarge-nightly' => 'command-xlarge-20221108',
                'command-xlarge-20221108' => 'command-r-plus',
            ],
            'mistral' => [
                'mistral-large-latest' => 'mistral-small-latest',
                'mistral-small-latest' => 'mistral-large-latest',
            ],
            'ollama' => [
                'llama3' => 'llama2',
                'llama2' => 'llama1',
                'llama1' => 'llama3',
            ],
        ]
    ];

    /**
     * Get extension configuration from LocalConfiguration.php
     *
     * @return array
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    public static function getExtensionConfiguration(): array
    {
        return (array)GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('w3c_aiconnector');
    }

    /**
     * @param $key
     * @return mixed
     */
    public static function getDefaultConfiguration($key): mixed
    {
        return self::DEFAULT_CONFIGURATION[$key] ?? null;
    }
}
