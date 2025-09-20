<?php

declare(strict_types=1);

namespace W3code\W3cAiconnector\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use W3code\W3cAiconnector\Interface\AiConnectorInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class CohereService extends BaseService implements AiConnectorInterface
{
    private const API_ENDPOINT = 'https://api.cohere.ai/v1/chat';

    public function process(string $prompt, array $options = []): ?string
    {
        $extConf = GeneralUtility::makeInstance(ExtensionConfiguration::class)
            ->get('w3c_aiconnector');

        $apiKey = $options['apiKey'] ?? $extConf['cohereApiKey'] ?? '';
        $model = $options['model'] ?? $extConf['cohereModelName'] ?? self::DEFAULT_COHERE_MODEL;
        $maxTokens = $options['maxTokens'] ?? $extConf['cohereMaxTokens'] ?? self::DEFAULT_COHERE_MAX_TOKENS;
        $temperature = $options['temperature'] ?? (float)($extConf['cohereTemperature'] ?? self::DEFAULT_COHERE_TEMPERATURE);
        $p = $options['p'] ?? (float)($extConf['cohereP'] ?? self::DEFAULT_COHERE_P);
        $k = $options['k'] ?? (int)($extConf['cohereK'] ?? self::DEFAULT_COHERE_K);
        $frequencyPenalty = $options['frequencyPenalty'] ?? (float)($extConf['cohereFrequencyPenalty'] ?? self::DEFAULT_COHERE_FREQUENCY_PENALTY);
        $presencePenalty = $options['presencePenalty'] ?? (float)($extConf['coherePresencePenalty'] ?? self::DEFAULT_COHERE_PRESENCE_PENALTY);
        $stopSequences = $options['stopSequences'] ?? ($extConf['cohereStopSequences'] ? explode(',', $extConf['cohereStopSequences']) : self::DEFAULT_COHERE_STOP_SEQUENCES);
        $stream = $options['stream'] ?? (bool)($extConf['cohereStream'] ?? self::DEFAULT_COHERE_STREAM);
        $preamble = $options['preamble'] ?? $extConf['coherePreamble'] ?? self::DEFAULT_COHERE_PREAMBLE;
        $chatHistory = $options['chatHistory'] ?? []; // Cohere expects an array of messages for chat_history

        $logOptions = $options;
        if (isset($logOptions['apiKey'])) {
            $logOptions['apiKey'] = $this->maskApiKey($logOptions['apiKey']);
        }
        $this->logger->info('Cohere info: ', ['model' => $model, 'options' => $logOptions]);

        $jsonBody = [
            'model' => $model,
            'message' => $prompt, // The user's message to send to the model. Can be used instead of chat_history
        ];

        if (!empty($chatHistory)) {
            $jsonBody['chat_history'] = $chatHistory;
        }
        if (!empty($maxTokens)) {
            $jsonBody['max_tokens'] = $maxTokens;
        }
        if (isset($temperature)) {
            $jsonBody['temperature'] = $temperature;
        }
        if (isset($p)) {
            $jsonBody['p'] = $p;
        }
        if (isset($k)) {
            $jsonBody['k'] = $k;
        }
        if (isset($frequencyPenalty)) {
            $jsonBody['frequency_penalty'] = $frequencyPenalty;
        }
        if (isset($presencePenalty)) {
            $jsonBody['presence_penalty'] = $presencePenalty;
        }
        if (!empty($stopSequences)) {
            $jsonBody['stop_sequences'] = $stopSequences;
        }
        if (isset($stream)) {
            $jsonBody['stream'] = $stream;
        }
        if (!empty($preamble)) {
            $jsonBody['preamble'] = $preamble;
        }

        try {
            $client = new Client();
            $response = $client->post(self::API_ENDPOINT, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json'
                ],
                'json' => $jsonBody
            ]);
            $body = json_decode((string)$response->getBody(), true);
            return $body['text'] ?? null;
        } catch (RequestException $e) {
            $this->handleServiceRequestException('Cohere', $e, $apiKey, $logOptions, $model);
            return null;
        } catch (GuzzleException $e) {
            $this->handleServiceGuzzleException('Cohere', $e, $apiKey, $logOptions, $model);
            return null;
        }
    }
}