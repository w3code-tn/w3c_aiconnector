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

class GeminiService extends BaseService implements AiConnectorInterface
{
    private const API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/';
    private const API_URL_SUFFIX = ':generateContent?key=';
    private const API_URL_STREAM_SUFFIX = ':streamGenerateContent?key=';
    private array $params = [];

    protected LoggerInterface $logger;

    public function __construct(LogManagerInterface $logManager)
    {
        
        $this->logger = $logManager->getLogger(static::class);
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
            $this->handleServiceRequestException('Gemini', $e, $options['apiKey'], $logOptions, $options['model']);
            return null;
        } catch (GuzzleException $e) {
            $this->handleServiceGuzzleException('Gemini', $e, $options['apiKey'], $logOptions, $options['model']);
            return null;
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
                                // La structure de la réponse de cette API est identique
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
        } catch (GuzzleException $e) {
            $this->handleServiceGuzzleException('Gemini', $e, $options['apiKey'], $logOptions, $options['model'], true, $this->logger);
        }
    }
}