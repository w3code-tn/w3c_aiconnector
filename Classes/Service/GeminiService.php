<?php

declare(strict_types=1);

namespace W3code\W3cAiconnector\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use W3code\W3cAiconnector\Interface\AiConnectorInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class GeminiService extends BaseService implements AiConnectorInterface
{
    private const API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/';
    private const API_URL_SUFFIX = ':generateContent?key=';
    private const API_URL_STREAM_SUFFIX = ':streamGenerateContent?key=';

    public function process(string $prompt, array $options = []): ?string
    {
        $extConf = GeneralUtility::makeInstance(ExtensionConfiguration::class)
            ->get('w3c_aiconnector');

        $apiKey = $options['apiKey'] ?? $extConf['geminiApiKey'] ?? '';
        $model = $options['model'] ?? $extConf['geminiModelName'] ?? self::DEFAULT_GEMINI_MODEL;
        $stream = $options['stream'] ?? ($extConf['geminiStream'] ?? self::DEFAULT_GEMINI_STREAM);
        $stream = (bool)$stream;

        $generationConfig = [
            'temperature' => (float)($options['temperature'] ?? $extConf['geminiTemperature'] ?? self::DEFAULT_GEMINI_TEMPERATURE),
            'topP' => (float)($options['topP'] ?? $extConf['geminiTopP'] ?? self::DEFAULT_GEMINI_TOP_P),
            'topK' => (int)($options['topK'] ?? $extConf['geminiTopK'] ?? self::DEFAULT_GEMINI_TOP_K),
            'candidateCount' => (int)($options['candidateCount'] ?? $extConf['geminiCandidateCount'] ?? self::DEFAULT_GEMINI_CANDIDATE_COUNT),
            'maxOutputTokens' => (int)($options['maxOutputTokens'] ?? $extConf['geminiMaxOutputTokens'] ?? self::DEFAULT_GEMINI_MAX_OUTPUT_TOKENS),
            'stopSequences' => $options['stopSequences'] ?? ($extConf['geminiStopSequences'] ? explode(',', $extConf['geminiStopSequences']) : self::DEFAULT_GEMINI_STOP_SEQUENCES),
        ];

        $logOptions = $options;
        if (isset($logOptions['apiKey'])) {
            $logOptions['apiKey'] = $this->maskApiKey($logOptions['apiKey']);
        }
        $this->logger->info('Gemini info: ', ['model' => $model, 'options' => $logOptions]);

        $client = new Client();

        $apiUrlSuffix = $stream ? self::API_URL_STREAM_SUFFIX : self::API_URL_SUFFIX;

        try {
            $response = $client->post(self::API_URL . $model . $apiUrlSuffix . $apiKey, [
                'json' => [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt]
                            ]
                        ]
                    ],
                    'generationConfig' => $generationConfig
                ]
            ]);

            $body = json_decode((string)$response->getBody(), true);
            return $body['candidates'][0]['content']['parts'][0]['text'] ?? null;

        } catch (RequestException $e) {
            $this->handleServiceRequestException('Gemini', $e, $apiKey, $logOptions, $model);
            return null;
        } catch (GuzzleException $e) {
            $this->handleServiceGuzzleException('Gemini', $e, $apiKey, $logOptions, $model);
            return null;
        }
    }
}