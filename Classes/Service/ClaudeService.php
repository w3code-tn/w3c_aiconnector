<?php

declare(strict_types=1);

namespace W3code\W3cAiconnector\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use W3code\W3cAiconnector\Interface\AiConnectorInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ClaudeService extends BaseService implements AiConnectorInterface
{
    private const API_ENDPOINT = 'https://api.anthropic.com/v1/messages';

    public function process(string $prompt, array $options = []): ?string
    {
        $extConf = GeneralUtility::makeInstance(ExtensionConfiguration::class)
            ->get('w3c_aiconnector');

        $apiKey = $options['apiKey'] ?? $extConf['claudeApiKey'] ?? '';
        $model = $options['model'] ?? $extConf['claudeModelName'] ?? self::DEFAULT_CLAUDE_MODEL;
        $apiVersion = $options['apiVersion'] ?? $extConf['claudeApiVersion'] ?? self::DEFAULT_CLAUDE_API_VERSION;
        $maxTokens = $options['maxTokens'] ?? $extConf['claudeMaxTokens'] ?? self::DEFAULT_CLAUDE_MAX_TOKENS;
        $system = $options['system'] ?? $extConf['claudeSystem'] ?? self::DEFAULT_CLAUDE_SYSTEM;
        $stopSequences = $options['stopSequences'] ?? ($extConf['claudeStopSequences'] ? explode(',', $extConf['claudeStopSequences']) : self::DEFAULT_CLAUDE_STOP_SEQUENCES);
        $stream = $options['stream'] ?? (bool)($extConf['claudeStream'] ?? self::DEFAULT_CLAUDE_STREAM);
        $temperature = $options['temperature'] ?? (float)($extConf['claudeTemperature'] ?? self::DEFAULT_CLAUDE_TEMPERATURE);
        $topP = $options['topP'] ?? (float)($extConf['claudeTopP'] ?? self::DEFAULT_CLAUDE_TOP_P);
        $topK = $options['topK'] ?? (int)($extConf['claudeTopK'] ?? self::DEFAULT_CLAUDE_TOP_K);

        $logOptions = $options;
        if (isset($logOptions['apiKey'])) {
            $logOptions['apiKey'] = $this->maskApiKey($logOptions['apiKey']);
        }
        $this->logger->info('Claude info: ', ['model' => $model, 'options' => $logOptions]);

        $jsonBody = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'stream' => $stream,
        ];

        if (!empty($system)) {
            $jsonBody['system'] = $system;
        }
        if (!empty($stopSequences)) {
            $jsonBody['stop_sequences'] = $stopSequences;
        }
        if (isset($temperature)) {
            $jsonBody['temperature'] = $temperature;
        }
        if (isset($topP)) {
            $jsonBody['top_p'] = $topP;
        }
        if (isset($topK)) {
            $jsonBody['top_k'] = $topK;
        }

        try {
            $client = new Client();
            $response = $client->post(self::API_ENDPOINT, [
                'headers' => [
                    'x-api-key' => $apiKey,
                    'anthropic-version' => $apiVersion,
                    'Content-Type' => 'application/json'
                ],
                'json' => $jsonBody
            ]);
            $body = json_decode((string)$response->getBody(), true);
            // Adapte la clé selon la réponse Claude
            return $body['content'][0]['text'] ?? null;
        } catch (RequestException $e) {
            $this->handleServiceRequestException('Claude', $e, $apiKey, $logOptions, $model);
            return null;
        } catch (GuzzleException $e) {
            $this->handleServiceGuzzleException('Claude', $e, $apiKey, $logOptions, $model);
            return null;
        }
    }
}