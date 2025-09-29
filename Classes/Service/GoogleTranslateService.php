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

class GoogleTranslateService extends BaseService implements AiConnectorInterface
{
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
            'apiKey' => $extConf['googleTranslateApiKey'] ?? '',
            'targetLang' => $extConf['googleTranslateTargetLang'] ?? self::DEFAULT_GOOGLE_TRANSLATE_TARGET_LANG,
            'sourceLang' => $extConf['googleTranslateSourceLang'] ?? self::DEFAULT_GOOGLE_TRANSLATE_SOURCE_LANG,
            'format' => $extConf['googleTranslateFormat'] ?? self::DEFAULT_GOOGLE_TRANSLATE_FORMAT,
            'model' => $extConf['googleTranslateModel'] ?? self::DEFAULT_GOOGLE_TRANSLATE_MODEL,
            'cid' => $extConf['googleTranslateCid'] ?? self::DEFAULT_GOOGLE_TRANSLATE_CID,
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
        $this->logger->info('Google Translate info: ', ['targetLang' => $options['targetLang'], 'options' => $logOptions]);

        try {
            $client = new Client();
            $requestBody = [
                'q' => $prompt,
                'target' => $options['targetLang'],
            ];

            if (!empty($options['sourceLang'])) {
                $requestBody['source'] = $options['sourceLang'];
            }
            if (!empty($options['format'])) {
                $requestBody['format'] = $options['format'];
            }
            if (!empty($options['model'])) {
                $requestBody['model'] = $options['model'];
            }
            if (!empty($options['cid'])) {
                $requestBody['cid'] = $options['cid'];
            }

            $response = $client->post(self::DEFAULT_GOOGLE_TRANSLATE_API_ENDPOINT . '?key=' . $options['apiKey'], [
                'json' => $requestBody
            ]);
            $body = json_decode((string)$response->getBody(), true);
            return $body['data']['translations'][0]['translatedText'] ?? null;
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                if ( ($statusCode === 429 || $statusCode === 500 || $statusCode === 503) && $this->retryCount < $this->maxRetries) {
                    $this->retryCount++;
                    $this->logger->warning('Google Translate 429, 500 or 503 error', ['options' => $logOptions]);
                    sleep(5); // Wait 5 seconds before retrying
                    $this->process($prompt, $options);
                }
            }
            $this->handleServiceRequestException('Google Translate', $e, $options['apiKey'], $logOptions, null, true, $this->logger);
            return '{error: "Google Translate - ' . $this->languageService->sL('LLL:EXT:w3c_aiconnector/Resources/Private/Language/locallang.xlf:not_available') . '"}';
        } catch (GuzzleException $e) {
            $this->handleServiceGuzzleException('Google Translate', $e, $options['apiKey'], $logOptions, null, true, $this->logger);
            return '{error: "Google Translate - ' . $this->languageService->sL('LLL:EXT:w3c_aiconnector/Resources/Private/Language/locallang.xlf:not_available') . '"}';
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