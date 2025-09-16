<?php

declare(strict_types=1);

namespace W3code\W3cAiconnector\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use W3code\W3cAiconnector\Interface\AiConnectorInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class DeepLService implements AiConnectorInterface
{
    private const API_ENDPOINT = 'https://api.deepl.com/v2/translate';

    public function process(string $prompt, array $options = []): ?string
    {
        $extConf = GeneralUtility::makeInstance(ExtensionConfiguration::class)
            ->get('w3c_aiconnector');

        $apiKey = $extConf['deeplApiKey'] ?? '';
        $targetLang = $options['target_lang'] ?? 'EN';

        try {
            $client = new Client();
            $response = $client->post(self::API_ENDPOINT, [
                'form_params' => [
                    'auth_key' => $apiKey,
                    'text' => $prompt,
                    'target_lang' => $targetLang,
                ]
            ]);
            $body = json_decode((string)$response->getBody(), true);
            return $body['translations'][0]['text'] ?? null;
        } catch (GuzzleException $e) {
            return null;
        }
    }
}