<?php

declare(strict_types=1);

namespace W3code\W3cAiconnector\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use W3code\W3cAiconnector\Interface\AiConnectorInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class GeminiService implements AiConnectorInterface
{
    private const API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/';
    private const API_URL_SUFFIX = ':generateContent?key=';

    public function process(string $prompt, array $options = []): ?string
    {
        $extConf = GeneralUtility::makeInstance(ExtensionConfiguration::class)
            ->get('w3c_aiconnector');

        $apiKey = $extConf['geminiApiKey'] ?? '';
        $modelName = $extConf['geminiModelName'] ?? 'gemini-2.0-flash';

        $client = new Client();

        try {
            $response = $client->post(self::API_URL . $modelName . self::API_URL_SUFFIX . $apiKey, [
                'json' => [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt]
                            ]
                        ]
                    ]
                ]
            ]);

            $body = json_decode((string)$response->getBody(), true);
            return $body['candidates'][0]['content']['parts'][0]['text'] ?? null;

        } catch (GuzzleException $e) {
            $GLOBALS['BE_USER']->writelog(4, 0, 0, 0, $e->getMessage(), "w3c_aiconnector");
            return null;
        }
    }
}