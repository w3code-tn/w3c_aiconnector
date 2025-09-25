<?php

declare(strict_types=1);

namespace W3code\W3cAiconnector\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use W3code\W3cAiconnector\Interface\AiConnectorInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Log\LogManagerInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Localization\LanguageService;

class GeminiService extends BaseService implements AiConnectorInterface
{
    private const API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/';
    private const API_URL_SUFFIX = ':generateContent?key=';
    private const API_URL_STREAM_SUFFIX = ':streamGenerateContent?key=';
    private array $params = [];

    protected LoggerInterface $logger;
    protected LanguageServiceFactory $languageServiceFactory;
    protected ?LanguageService $languageService = null;

    protected int $retryCount = 0;

    public function __construct(
        LogManagerInterface $logManager, 
        Context $context,
        LanguageServiceFactory $languageServiceFactory
    )
    {
        $this->languageServiceFactory = $languageServiceFactory;
        $this->logger = $logManager->getLogger(static::class);
        $site = $GLOBALS['TYPO3_REQUEST']?->getAttribute('site');
        $currentLanguage = $site->getLanguageById($context->getAspect('language')->getId());
        $this->languageService = $this->languageServiceFactory->createFromSiteLanguage($currentLanguage);

        // intialiser les parametres comme apikey, model, temperature, topP, topK, candidateCount, maxOutputTokens, stopSequences
        $extConf = GeneralUtility::makeInstance(ExtensionConfiguration::class)
            ->get('w3c_aiconnector');

        $this->params = [
            'apiKey' => $extConf['geminiApiKey'] ?? '',
            'model' => $extConf['geminiModelName'] ?? self::DEFAULT_GEMINI_MODEL,
            'generationConfig' => [
                'temperature' => (float)($extConf['geminiTemperature'] ?? self::DEFAULT_GEMINI_TEMPERATURE),
                'topP' => (float)($extConf['geminiTopP'] ?? self::DEFAULT_GEMINI_TOP_P),
                'topK' => (int)($extConf['geminiTopK'] ?? self::DEFAULT_GEMINI_TOP_K),
                'candidateCount' => (int)($extConf['geminiCandidateCount'] ?? self::DEFAULT_GEMINI_CANDIDATE_COUNT),
                'maxOutputTokens' => (int)($extConf['geminiMaxOutputTokens'] ?? self::DEFAULT_GEMINI_MAX_OUTPUT_TOKENS),
                'stopSequences' => $extConf['geminiStopSequences'] ? explode(',', $extConf['geminiStopSequences']) : self::DEFAULT_GEMINI_STOP_SEQUENCES,
            ],
            'chunkSize' => (int)($extConf['geminiChunkSize'] ?? self::DEFAULT_STREAM_CHUNK_SIZE),         
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

        $this->logger->info('Gemini info: ', ['model' => $options['model'], 'options' => $logOptions]);

        $client = new Client();;

        try {
            $response = $client->post(self::API_URL . $options['model'] . self::API_URL_SUFFIX . $options['apiKey'], [
                'json' => [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt]
                            ]
                        ]
                    ],
                    'generationConfig' => $options['generationConfig']
                ]
            ]);

            $body = json_decode((string)$response->getBody(), true);

            return $body['candidates'][0]['content']['parts'][0]['text'] ?? null;

        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                if ( ($statusCode === 429 || $statusCode === 503 ) && $this->retryCount < $this->maxRetries) {
                    $this->retryCount++;
                    $this->logger->warning('Gemini 429 or 503 error', ['model' => $options['model'], 'options' => $logOptions]);
                    $options['model'] = $this->fallbackModel('gemini', $options['model']);
                    $this->process($prompt, $options);
                }
            }
            $this->handleServiceRequestException('Gemini', $e, $options['apiKey'], $logOptions, $options['model'], true, $this->logger);
            return '{error: "Gemini - ' . $this->languageService->sL('LLL:EXT:w3c_aiconnector/Resources/Private/Language/locallang.xlf:not_available') . '"}';
        } catch (GuzzleException $e) {
            $this->handleServiceGuzzleException('Gemini', $e, $options['apiKey'], $logOptions, $options['model'], true, $this->logger);
            return '{error: "Gemini - ' . $this->languageService->sL('LLL:EXT:w3c_aiconnector/Resources/Private/Language/locallang.xlf:not_available') . '"}';
        }
    }

    /**
     * Appelle l'API Gemini en mode streaming et traite la réponse.
     *
     * @param string $prompt Le prompt à envoyer au modèle.
     * @param array $options Options pour surcharger la configuration (apiKey, model, etc.).
     */
    public function streamProcess(string $prompt, array $options = []): \Generator
    {
        $options = $this->overrideParams($options, $this->params);

        $logOptions = $options;
        if (isset($logOptions['apiKey'])) {
            $logOptions['apiKey'] = $this->maskApiKey($logOptions['apiKey']);
        }

        $this->logger->info('Gemini stream info: ', ['model' => $options['model'], 'options' => $logOptions]);

        $client = new Client();
        $url = self::API_URL . $options['model'] . self::API_URL_STREAM_SUFFIX . $options['apiKey'];

        $payload = [
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $prompt]]]
            ],
            'generationConfig' => $options['generationConfig'],
        ];

        try {
            $response = $client->request('POST', $url, [
                'json' => $payload,
                'stream' => true, // Option Guzzle pour activer le streaming
            ]);

            $body = $response->getBody();
            $braceLevel = 0;
            $currentObject = '';

            // --- 4. Traitement du flux de réponse ---
            while (!$body->eof()) {
                $chunk = $body->read(self::DEFAULT_STREAM_CHUNK_SIZE);
                for ($i = 0; $i < strlen($chunk); $i++) {
                    $char = $chunk[$i];

                    if ($braceLevel > 0) $currentObject .= $char;
                    if ($char === '{') {
                        if ($braceLevel === 0) $currentObject = '{';
                        $braceLevel++;
                    } elseif ($char === '}') {
                        $braceLevel--;
                        if ($braceLevel === 0 && !empty($currentObject)) {
                            $data = json_decode($currentObject, true);
                            if (json_last_error() === JSON_ERROR_NONE) {
                                $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
                                if ($text) {
                                    yield $text;
                                    if (ob_get_level() > 0) ob_flush();
                                    flush();
                                }
                            }
                            $currentObject = '';
                        }
                    }
                }
            }

        } catch (RequestException $e) {
            $this->handleServiceRequestException('Gemini', $e, $options['apiKey'], $logOptions, $options['model'], true, $this->logger);
            yield 'Gemini - ' . $this->languageService->sL('LLL:EXT:w3c_aiconnector/Resources/Private/Language/locallang.xlf:not_available');
        } catch (GuzzleException $e) {
            $this->handleServiceGuzzleException('Gemini', $e, $options['apiKey'], $logOptions, $options['model'], true, $this->logger);
            yield 'Gemini - ' . $this->languageService->sL('LLL:EXT:w3c_aiconnector/Resources/Private/Language/locallang.xlf:not_available');
        }
    }
}