<?php

declare(strict_types=1);

namespace W3code\W3cAiconnector\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use W3code\W3cAiconnector\Interface\AiConnectorInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class DeepLService extends BaseService implements AiConnectorInterface
{
    private const API_ENDPOINT = 'https://api.deepl.com/v2/translate';

    public function process(string $prompt, array $options = []): ?string
    {
        $extConf = GeneralUtility::makeInstance(ExtensionConfiguration::class)
            ->get('w3c_aiconnector');

        $apiKey = $options['apiKey'] ?? $extConf['deeplApiKey'] ?? '';
        $targetLang = $options['target_lang'] ?? self::DEFAULT_DEEPL_TARGET_LANG;
        $sourceLang = $options['source_lang'] ?? $extConf['deeplSourceLang'] ?? self::DEFAULT_DEEPL_SOURCE_LANG;
        $splitSentences = $options['split_sentences'] ?? $extConf['deeplSplitSentences'] ?? self::DEFAULT_DEEPL_SPLIT_SENTENCES;
        $preserveFormatting = $options['preserve_formatting'] ?? (bool)($extConf['deeplPreserveFormatting'] ?? self::DEFAULT_DEEPL_PRESERVE_FORMATTING);
        $formality = $options['formality'] ?? $extConf['deeplFormality'] ?? self::DEFAULT_DEEPL_FORMALITY;
        $glossaryId = $options['glossary_id'] ?? $extConf['deeplGlossaryId'] ?? self::DEFAULT_DEEPL_GLOSSARY_ID;
        $tagHandling = $options['tag_handling'] ?? $extConf['deeplTagHandling'] ?? self::DEFAULT_DEEPL_TAG_HANDLING;
        $outlineDetection = $options['outline_detection'] ?? (bool)($extConf['deeplOutlineDetection'] ?? self::DEFAULT_DEEPL_OUTLINE_DETECTION);
        $nonSplittingTags = $options['non_splitting_tags'] ?? $extConf['deeplNonSplittingTags'] ?? self::DEFAULT_DEEPL_NON_SPLITTING_TAGS;

        $logOptions = $options;
        if (isset($logOptions['auth_key'])) {
            $logOptions['auth_key'] = $this->maskApiKey($logOptions['auth_key']);
        }
        $this->logger->info('DeepL info: ', ['target_lang' => $targetLang, 'options' => $logOptions]);

        $formParams = [
            'auth_key' => $apiKey,
            'text' => $prompt,
            'target_lang' => $targetLang,
        ];

        if (!empty($sourceLang)) {
            $formParams['source_lang'] = $sourceLang;
        }
        if (!empty($splitSentences)) {
            $formParams['split_sentences'] = $splitSentences;
        }
        if ($preserveFormatting) {
            $formParams['preserve_formatting'] = 1;
        }
        if (!empty($formality)) {
            $formParams['formality'] = $formality;
        }
        if (!empty($glossaryId)) {
            $formParams['glossary_id'] = $glossaryId;
        }
        if (!empty($tagHandling)) {
            $formParams['tag_handling'] = $tagHandling;
        }
        if ($outlineDetection) {
            $formParams['outline_detection'] = 1;
        }
        if (!empty($nonSplittingTags)) {
            $formParams['non_splitting_tags'] = $nonSplittingTags;
        }

        try {
            $client = new Client();
            $response = $client->post(self::API_ENDPOINT, [
                'form_params' => $formParams
            ]);
            $body = json_decode((string)$response->getBody(), true);
            return $body['translations'][0]['text'] ?? null;
        } catch (RequestException $e) {
            $this->handleServiceRequestException('DeepL', $e, $apiKey, $logOptions, null, false);
            return null;
        } catch (GuzzleException $e) {
            $this->handleServiceGuzzleException('DeepL', $e, $apiKey, $logOptions, null, false);
            return null;
        }
    }
}