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
            'maxInputTokensAllowed' => (int)($extConf['geminiMaxInputTokensAllowed'] ?? self::MAX_INPUT_TOKENS_ALLOWED),   
        ];
        $this->maxRetries = (int)($extConf['maxRetries'] ?? self::DEFAULT_MAX_RETRIES);

        if($extConf['geminiFallbackModels'] ?? false) {
            $this->fallbacks['gemini'] = $this->getExtConfFallbackModel($extConf['geminiFallbackModels']);
        }
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
                    return $this->process($prompt, $options);
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
        // Correction de la construction de l'URL pour séparer le suffixe de la clé API
        $url = self::API_URL . $options['model'] . self::API_URL_STREAM_SUFFIX  . $options['apiKey'];

        $payload = [
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $prompt]]]
            ],
            'generationConfig' => $options['generationConfig'],
        ];

        try {
            $response = $client->request('POST', $url, [
                'json' => $payload,
                'stream' => true,
            ]);

            $body = $response->getBody();
            $buffer = '';

            while (!$body->eof()) {
                $buffer .= $body->read($options['chunkSize'] ?? 2048); // Lire un morceau

                // NOUVELLE LOGIQUE : On cherche des objets JSON complets dans le buffer
                // On considère qu'un objet se termine par '}'. On split donc par ce caractère.

                $tempBuffer = substr($buffer, strpos($buffer, '{'));
                $potentialObjects = explode('}',  $tempBuffer);
                $lastElement = array_pop($potentialObjects);
                $potentialJson = implode('}', $potentialObjects).'}';

                if(json_validate($potentialJson)){
                    // Le dernier élément du tableau est ce qui reste après le dernier '}'.
                    // C'est soit une chaîne vide, soit un début d'objet JSON. On le garde pour la prochaine itération.
                    $buffer = $lastElement;

                    $decoded = json_decode($potentialJson, true);

                    if (json_last_error() === JSON_ERROR_NONE) {
                        // C'est un JSON valide ! On l'exploite.
                        if (isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
                            yield $decoded['candidates'][0]['content']['parts'][0]['text'];
                        }

                        // La condition pour arrêter reste la même et est toujours aussi importante.
                        if (isset($decoded['usageMetadata'])) {
                          //  break; // Sortir de la boucle foreach ET de la boucle while
                        }
                    }
                }
            }   
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                if (($statusCode === 429 || $statusCode === 503) && $this->retryCount < $this->maxRetries && !empty($this->fallbacks['gemini'])) {
                    $this->retryCount++;
                    $this->logger->warning('Gemini 429 or 503 error, trying fallback', ['model' => $options['model'], 'options' => $logOptions]);
                    $options['model'] = $this->fallbackModel('gemini', $options['model']);
                    yield from $this->streamProcess($prompt, $options);
                    return;
                }
            }
            $this->handleServiceRequestException('Gemini', $e, $options['apiKey'], $logOptions, $options['model'], true, $this->logger);
            yield 'Gemini - ' . $this->languageService->sL('LLL:EXT:w3c_aiconnector/Resources/Private/Language/locallang.xlf:not_available');
        } catch (GuzzleException $e) {
            $this->handleServiceGuzzleException('Gemini', $e, $options['apiKey'], $logOptions, $options['model'], true, $this->logger);
            yield 'Gemini - ' . $this->languageService->sL('LLL:EXT:w3c_aiconnector/Resources/Private/Language/locallang.xlf:not_available');
        }
    }
}
