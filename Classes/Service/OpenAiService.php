<?php

declare(strict_types=1);

namespace W3code\W3cAiconnector\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use W3code\W3cAiconnector\Interface\AiConnectorInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class OpenAiService extends BaseService implements AiConnectorInterface
{
    public function process(string $prompt, array $options = []): ?string
    {
        $extConf = GeneralUtility::makeInstance(ExtensionConfiguration::class)
            ->get('w3c_aiconnector');

        $apiKey = $options['apiKey'] ?? $extConf['openAiApiKey'] ?? '';
        $model = $options['model'] ?? $extConf['openAiModelName'] ?? self::DEFAULT_OPENAI_MODEL;
        $temperature = (float)($options['temperature'] ?? $extConf['openAiTemperature'] ?? self::DEFAULT_OPENAI_TEMPERATURE);
        $topP = (float)($options['topP'] ?? $extConf['openAiTopP'] ?? self::DEFAULT_OPENAI_TOP_P);
        $maxTokens = (int)($options['maxTokens'] ?? $extConf['openAiMaxTokens'] ?? self::DEFAULT_OPENAI_MAX_TOKENS);
        $stop = $options['stop'] ?? ($extConf['openAiStop'] ? GeneralUtility::trimExplode(',', $extConf['openAiStop'], true) : self::DEFAULT_OPENAI_STOP);
        $stream = (bool)($options['stream'] ?? $extConf['openAiStream'] ?? self::DEFAULT_OPENAI_STREAM);
        $presencePenalty = (float)($options['presencePenalty'] ?? $extConf['openAiPresencePenalty'] ?? self::DEFAULT_OPENAI_PRESENCE_PENALTY);
        $frequencyPenalty = (float)($options['frequencyPenalty'] ?? $extConf['openAiFrequencyPenalty'] ?? self::DEFAULT_OPENAI_FREQUENCY_PENALTY);

        $logOptions = $options;
        if (isset($logOptions['apiKey'])) {
            $logOptions['apiKey'] = $this->maskApiKey($logOptions['apiKey']);
        }
        $this->logger->info('OpenAI info: ', [
            'model' => $model,
            'options' => $logOptions,
            'temperature' => $temperature,
            'topP' => $topP,
            'maxTokens' => $maxTokens,
            'stop' => $stop,
            'stream' => $stream,
            'presencePenalty' => $presencePenalty,
            'frequencyPenalty' => $frequencyPenalty,
        ]);

        try {
            $client = new Client();
            $requestBody = [
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => $temperature,
                'top_p' => $topP,
                'max_tokens' => $maxTokens,
                'stream' => $stream,
                'presence_penalty' => $presencePenalty,
                'frequency_penalty' => $frequencyPenalty,
            ];

            if (!empty($stop)) {
                $requestBody['stop'] = $stop;
            }

            $response = $client->post(self::DEFAULT_OPENAI_API_ENDPOINT, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json'
                ],
                'json' => $requestBody
            ]);
            $body = json_decode((string)$response->getBody(), true);
            return $body['choices'][0]['message']['content'] ?? null;
        } catch (RequestException $e) {
            $this->handleServiceRequestException('OpenAI', $e, $apiKey, $logOptions, $model, false);
            return null;
        } catch (GuzzleException $e) {
            $this->handleServiceGuzzleException('OpenAI', $e, $apiKey, $logOptions, $model, false);
            return null;
        }
    }
}