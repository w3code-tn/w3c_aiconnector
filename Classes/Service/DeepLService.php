<?php

declare(strict_types=1);

namespace W3code\W3cAiconnector\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Log\LogManagerInterface;
use W3code\W3cAiconnector\Interface\AiConnectorInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Localization\LanguageService;

class DeepLService extends BaseService implements AiConnectorInterface
{
    private const API_ENDPOINT = 'https://api.deepl.com/v2/translate';
    private const FREE_API_ENDPOINT = 'https://api-free.deepl.com/v2/translate'; 
    protected LoggerInterface $logger;
    protected LanguageServiceFactory $languageServiceFactory;
    protected ?LanguageService $languageService = null;

    protected int $retryCount = 0;

    public function __construct(
        LogManagerInterface $logManager,
        Context $context,
        LanguageServiceFactory $languageServiceFactory
    ) {
        $this->languageServiceFactory = $languageServiceFactory;
        $this->logger = $logManager->getLogger(static::class);
        $site = $GLOBALS['TYPO3_REQUEST']?->getAttribute('site');
        $currentLanguage = $site->getLanguageById($context->getAspect('language')->getId());
        $this->languageService = $this->languageServiceFactory->createFromSiteLanguage($currentLanguage);

        $extConf = GeneralUtility::makeInstance(ExtensionConfiguration::class)
            ->get('w3c_aiconnector');

        $this->params = [
            'apiKey' => $extConf['deeplApiKey'] ?? '',
            'target_lang' => self::DEFAULT_DEEPL_TARGET_LANG,
            'source_lang' => $extConf['deeplSourceLang'] ?? self::DEFAULT_DEEPL_SOURCE_LANG,
            'split_sentences' => $extConf['deeplSplitSentences'] ?? self::DEFAULT_DEEPL_SPLIT_SENTENCES,
            'preserve_formatting' => (bool)($extConf['deeplPreserveFormatting'] ?? self::DEFAULT_DEEPL_PRESERVE_FORMATTING),
            'formality' => $extConf['deeplFormality'] ?? self::DEFAULT_DEEPL_FORMALITY,
            'glossary_id' => $extConf['deeplGlossaryId'] ?? self::DEFAULT_DEEPL_GLOSSARY_ID,
            'tag_handling' => $extConf['deeplTagHandling'] ?? self::DEFAULT_DEEPL_TAG_HANDLING,
            'outline_detection' => (bool)($extConf['deeplOutlineDetection'] ?? self::DEFAULT_DEEPL_OUTLINE_DETECTION),
            'non_splitting_tags' => $extConf['deeplNonSplittingTags'] ?? self::DEFAULT_DEEPL_NON_SPLITTING_TAGS,
            'apiVersion' => $extConf['deeplApiVersion'] ?? 'free',
        ];
        $this->maxRetries = (int)($extConf['maxRetries'] ?? self::DEFAULT_MAX_RETRIES);
    }

    public function process(string $prompt, array $options = []): ?string
    {
        $options = $this->overrideParams($options, $this->params);

        $logOptions = $options;
        if (isset($logOptions['apiKey'])) {
            $logOptions['apiKey'] = $this->maskApiKey($logOptions['apiKey']);
        }
        $this->logger->info('DeepL info: ', ['target_lang' => $options['target_lang'], 'options' => $logOptions]);

        $endpoint = self::API_ENDPOINT;
        if (($options['apiVersion'] ?? 'free') === 'free') {
            $endpoint = self::FREE_API_ENDPOINT;
        }

        $formParams = [
            'auth_key' => $options['apiKey'],
            'text' => $prompt,
            'target_lang' => $options['target_lang'],
        ];

        if (!empty($options['source_lang'])) {
            $formParams['source_lang'] = $options['source_lang'];
        }
        if (!empty($options['split_sentences'])) {
            $formParams['split_sentences'] = $options['split_sentences'];
        }
        if ($options['preserve_formatting']) {
            $formParams['preserve_formatting'] = 1;
        }
        if (!empty($options['formality'])) {
            $formParams['formality'] = $options['formality'];
        }
        if (!empty($options['glossary_id'])) {
            $formParams['glossary_id'] = $options['glossary_id'];
        }
        if (!empty($options['tag_handling'])) {
            $formParams['tag_handling'] = $options['tag_handling'];
        }
        if ($options['outline_detection']) {
            $formParams['outline_detection'] = 1;
        }
        if (!empty($options['non_splitting_tags'])) {
            $formParams['non_splitting_tags'] = $options['non_splitting_tags'];
        }

        try {
            $client = new Client();
            $response = $client->post($endpoint, [
                'form_params' => $formParams
            ]);
            $body = json_decode((string)$response->getBody(), true);
            return $body['translations'][0]['text'] ?? null;
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                if ( ($statusCode === 429 || $statusCode === 456) && $this->retryCount < $this->maxRetries) {
                    $this->retryCount++;
                    $this->logger->warning('DeepL 429 or 456 error', ['options' => $logOptions]);
                    sleep(5); // Wait 5 seconds before retrying
                    $this->process($prompt, $options);
                }
            }
            $this->handleServiceRequestException('DeepL', $e, $options['apiKey'], $logOptions, null, true, $this->logger);
            return '{error: "DeepL - ' . $this->languageService->sL('LLL:EXT:w3c_aiconnector/Resources/Private/Language/locallang.xlf:not_available') . '"}';
        } catch (GuzzleException $e) {
            $this->handleServiceGuzzleException('DeepL', $e, $options['apiKey'], $logOptions, null, true, $this->logger);
            return '{error: "DeepL - ' . $this->languageService->sL('LLL:EXT:w3c_aiconnector/Resources/Private/Language/locallang.xlf:not_available') . '"}';
        }
    }

    public function streamProcess(string $prompt, array $options = []): \Generator
    {
        $result = $this->process($prompt, $options);
        if ($result === null) {
            yield '';
            return;
        }

        if (str_starts_with($result, '{error:')) {
            yield $result;
            return;
        }

        // Découpe le texte en phrases ou en morceaux de 50 caractères
        $chunkSize = 50;
        $length = strlen($result);
        for ($i = 0; $i < $length; $i += $chunkSize) {
            yield mb_substr($result, $i, $chunkSize);
            // Optionnel : flush pour forcer l’envoi
            if (ob_get_level() > 0) ob_flush();
            flush();
        }
    }
}