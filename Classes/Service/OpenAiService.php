<?php

declare(strict_types=1);

namespace W3code\W3cAiconnector\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use W3code\W3cAiconnector\Interface\AiConnectorInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class OpenAiService implements AiConnectorInterface
{
    private const API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    public function process(string $prompt, array $options = []): ?string
    {
        $extConf = GeneralUtility::makeInstance(ExtensionConfiguration::class)
            ->get('w3c_aiconnector');

        $apiKey = $extConf['openAiApiKey'] ?? '';
        $modelName = $extConf['openAiModelName'] ?? 'gpt-4o';

        try {
            $client = new Client();
            $response = $client->post(self::API_ENDPOINT, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                    'model' => $modelName,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ]
                ]
            ]);
            $body = json_decode((string)$response->getBody(), true);
            return $body['choices'][0]['message']['content'] ?? null;
        } catch (GuzzleException $e) {
            return null;
        }
    }
}