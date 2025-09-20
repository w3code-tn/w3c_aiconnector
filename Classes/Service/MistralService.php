<?php

declare(strict_types=1);

namespace W3code\W3cAiconnector\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use W3code\W3cAiconnector\Interface\AiConnectorInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class MistralService extends BaseService implements AiConnectorInterface
{
    public function process(string $prompt, array $options = []): ?string
    {
        $extConf = GeneralUtility::makeInstance(ExtensionConfiguration::class)
            ->get('w3c_aiconnector');

        $apiKey = $options['apiKey'] ?? $extConf['mistralApiKey'] ?? '';
        $model = $options['model'] ?? $extConf['mistralModelName'] ?? self::DEFAULT_MISTRAL_MODEL;
        $temperature = (float)($options['temperature'] ?? $extConf['mistralTemperature'] ?? self::DEFAULT_MISTRAL_TEMPERATURE);
        $topP = (float)($options['topP'] ?? $extConf['mistralTopP'] ?? self::DEFAULT_MISTRAL_TOP_P);
        $maxTokens = (int)($options['maxTokens'] ?? $extConf['mistralMaxTokens'] ?? self::DEFAULT_MISTRAL_MAX_TOKENS);
        $stop = $options['stop'] ?? ($extConf['mistralStop'] ? GeneralUtility::trimExplode(',', $extConf['mistralStop'], true) : self::DEFAULT_MISTRAL_STOP);
        $randomSeed = (int)($options['randomSeed'] ?? $extConf['mistralRandomSeed'] ?? self::DEFAULT_MISTRAL_RANDOM_SEED);
        $stream = (bool)($options['stream'] ?? $extConf['mistralStream'] ?? self::DEFAULT_MISTRAL_STREAM);
        $safePrompt = (bool)($options['safePrompt'] ?? $extConf['mistralSafePrompt'] ?? self::DEFAULT_MISTRAL_SAFE_PROMPT);

        $logOptions = $options;
        if (isset($logOptions['apiKey'])) {
            $logOptions['apiKey'] = $this->maskApiKey($logOptions['apiKey']);
        }
        $this->logger->info('Mistral info: ', [
            'model' => $model,
            'options' => $logOptions,
            'temperature' => $temperature,
            'topP' => $topP,
            'maxTokens' => $maxTokens,
            'stop' => $stop,
            'randomSeed' => $randomSeed,
            'stream' => $stream,
            'safePrompt' => $safePrompt,
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
                'random_seed' => $randomSeed,
                'stream' => $stream,
                'safe_prompt' => $safePrompt,
            ];

            if (!empty($stop)) {
                $requestBody['stop'] = $stop;
            }

            $response = $client->post(self::DEFAULT_MISTRAL_API_ENDPOINT, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json'
                ],
                'json' => $requestBody
            ]);
            $body = json_decode((string)$response->getBody(), true);
            return $body['choices'][0]['message']['content'] ?? null;
        } catch (RequestException $e) {
            $this->handleServiceRequestException('Mistral', $e, $apiKey, $logOptions, $model, false);
            return null;
        } catch (GuzzleException $e) {
            $this->handleServiceGuzzleException('Mistral', $e, $apiKey, $logOptions, $model, false);
            return null;
        }
    }
}