<?php

declare(strict_types=1);

namespace W3code\W3cAiconnector\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use W3code\W3cAiconnector\Interface\AiConnectorInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ClaudeService implements AiConnectorInterface
{
    private const API_ENDPOINT = 'https://api.anthropic.com/v1/messages';

    public function process(string $prompt, array $options = []): ?string
    {
        $extConf = GeneralUtility::makeInstance(ExtensionConfiguration::class)
            ->get('w3c_aiconnector');

        $apiKey = $extConf['claudeApiKey'] ?? '';
        $modelName = $extConf['claudeModelName'] ?? 'claude-3-opus-20240229';

        try {
            $client = new Client();
            $response = $client->post(self::API_ENDPOINT, [
                'headers' => [
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                    'model' => $modelName,
                    'max_tokens' => 1024,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ]
                ]
            ]);
            $body = json_decode((string)$response->getBody(), true);
            // Adapte la clé selon la réponse Claude
            return $body['content'][0]['text'] ?? null;
        } catch (GuzzleException $e) {
            return null;
        }
    }
}