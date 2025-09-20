<?php

declare(strict_types=1);

namespace W3code\W3cAiconnector\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use W3code\W3cAiconnector\Interface\AiConnectorInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class GoogleTranslateService extends BaseService implements AiConnectorInterface
{
    public function process(string $prompt, array $options = []): ?string
    {
        $extConf = GeneralUtility::makeInstance(ExtensionConfiguration::class)
            ->get('w3c_aiconnector');

        $apiKey = $options['apiKey'] ?? $extConf['googleTranslateApiKey'] ?? '';
        $targetLang = $options['targetLang'] ?? $extConf['googleTranslateTargetLang'] ?? self::DEFAULT_GOOGLE_TRANSLATE_TARGET_LANG;
        $sourceLang = $options['sourceLang'] ?? $extConf['googleTranslateSourceLang'] ?? self::DEFAULT_GOOGLE_TRANSLATE_SOURCE_LANG;
        $format = $options['format'] ?? $extConf['googleTranslateFormat'] ?? self::DEFAULT_GOOGLE_TRANSLATE_FORMAT;
        $model = $options['model'] ?? $extConf['googleTranslateModel'] ?? self::DEFAULT_GOOGLE_TRANSLATE_MODEL;
        $cid = $options['cid'] ?? $extConf['googleTranslateCid'] ?? self::DEFAULT_GOOGLE_TRANSLATE_CID;

        $logOptions = $options;
        if (isset($logOptions['apiKey'])) {
            $logOptions['apiKey'] = $this->maskApiKey($logOptions['apiKey']);
        }
        $this->logger->info('Google Translate info: ', ['targetLang' => $targetLang, 'options' => $logOptions]);

        try {
            $client = new Client();
            $requestBody = [
                'q' => $prompt,
                'target' => $targetLang,
            ];

            if (!empty($sourceLang)) {
                $requestBody['source'] = $sourceLang;
            }
            if (!empty($format)) {
                $requestBody['format'] = $format;
            }
            if (!empty($model)) {
                $requestBody['model'] = $model;
            }
            if (!empty($cid)) {
                $requestBody['cid'] = $cid;
            }

            $response = $client->post(self::DEFAULT_GOOGLE_TRANSLATE_API_ENDPOINT . '?key=' . $apiKey, [
                'json' => $requestBody
            ]);
            $body = json_decode((string)$response->getBody(), true);
            return $body['data']['translations'][0]['translatedText'] ?? null;
        } catch (RequestException $e) {
            $this->handleServiceRequestException('Google Translate', $e, $apiKey, $logOptions, null, true);
            return null;
        } catch (GuzzleException $e) {
            $this->handleServiceGuzzleException('Google Translate', $e, $apiKey, $logOptions, null, true);
            return null;
        }
    }
}