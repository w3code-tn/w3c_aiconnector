<?php

declare(strict_types=1);

namespace W3code\W3cAiconnector\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use W3code\W3cAiconnector\Interface\AiConnectorInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class OllamaService implements AiConnectorInterface
{
    /**
     * @var string L'URL de votre service Ollama
     */
    private const API_ENDPOINT = 'http://ollama:11434';

    public function process(string $prompt, array $options = []): ?string
    {
        $extConf = GeneralUtility::makeInstance(ExtensionConfiguration::class)
            ->get('w3c_aiconnector');

        $modelName = $extConf['ollamaModelName'] ?? 'llama3';

        $client = new Client();

        try {
            $response = $client->post(self::API_ENDPOINT . '/api/generate', [
                'json' => [
                    'model' => $modelName,
                    'prompt' => $prompt,
                    'stream' => false
                ]
            ]);
            $body = json_decode((string)$response->getBody(), true);
            return $body['response'] ?? null;

        } catch (GuzzleException $e) {
            $GLOBALS['BE_USER']->writelog(2, 0, 0, 0, 'Ollama error '.$e->getMessage(), "w3c_aiconnector");
            return null;
        }
    }
}