<?php

declare(strict_types=1);

namespace W3code\W3cAiconnector\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use W3code\W3cAiconnector\Interface\AiConnectorInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class CohereService implements AiConnectorInterface
{
    private const API_ENDPOINT = 'https://api.cohere.ai/v1/chat';

    public function process(string $prompt, array $options = []): ?string
    {
        $extConf = GeneralUtility::makeInstance(ExtensionConfiguration::class)
            ->get('w3c_aiconnector');

        $apiKey = $extConf['cohereApiKey'] ?? '';
        $modelName = $extConf['cohereModelName'] ?? 'command-r-plus';

        try {
            $client = new Client();
            $response = $client->post(self::API_ENDPOINT, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                    'model' => $modelName,
                    'message' => $prompt,
                ]
            ]);
            $body = json_decode((string)$response->getBody(), true);
            return $body['text'] ?? null;
        } catch (GuzzleException $e) {
            return null;
        }
    }
}