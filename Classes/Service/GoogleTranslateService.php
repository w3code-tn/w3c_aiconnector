<?php

declare(strict_types=1);

namespace W3code\W3cAiconnector\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Log\LogManagerInterface;
use W3code\W3cAiconnector\Interface\AiConnectorInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class GoogleTranslateService extends BaseService implements AiConnectorInterface
{
    private array $params = [];
    protected LoggerInterface $logger;

    public function __construct(LogManagerInterface $logManager)
    {
        $this->logger = $logManager->getLogger(static::class);
        $extConf = GeneralUtility::makeInstance(ExtensionConfiguration::class)
            ->get('w3c_aiconnector');

        $this->params = [
            'apiKey' => $extConf['googleTranslateApiKey'] ?? '',
            'targetLang' => $extConf['googleTranslateTargetLang'] ?? self::DEFAULT_GOOGLE_TRANSLATE_TARGET_LANG,
            'sourceLang' => $extConf['googleTranslateSourceLang'] ?? self::DEFAULT_GOOGLE_TRANSLATE_SOURCE_LANG,
            'format' => $extConf['googleTranslateFormat'] ?? self::DEFAULT_GOOGLE_TRANSLATE_FORMAT,
            'model' => $extConf['googleTranslateModel'] ?? self::DEFAULT_GOOGLE_TRANSLATE_MODEL,
            'cid' => $extConf['googleTranslateCid'] ?? self::DEFAULT_GOOGLE_TRANSLATE_CID,
        ];
    }

    public function process(string $prompt, array $options = []): ?string
    {
        $options = $this->overrideParams($options, $this->params);

        $logOptions = $options;
        if (isset($logOptions['apiKey'])) {
            $logOptions['apiKey'] = $this->maskApiKey($logOptions['apiKey']);
        }
        $this->logger->info('Google Translate info: ', ['targetLang' => $options['targetLang'], 'options' => $logOptions]);

        try {
            $client = new Client();
            $requestBody = [
                'q' => $prompt,
                'target' => $options['targetLang'],
            ];

            if (!empty($options['sourceLang'])) {
                $requestBody['source'] = $options['sourceLang'];
            }
            if (!empty($options['format'])) {
                $requestBody['format'] = $options['format'];
            }
            if (!empty($options['model'])) {
                $requestBody['model'] = $options['model'];
            }
            if (!empty($options['cid'])) {
                $requestBody['cid'] = $options['cid'];
            }

            $response = $client->post(self::DEFAULT_GOOGLE_TRANSLATE_API_ENDPOINT . '?key=' . $options['apiKey'], [
                'json' => $requestBody
            ]);
            $body = json_decode((string)$response->getBody(), true);
            return $body['data']['translations'][0]['translatedText'] ?? null;
        } catch (RequestException $e) {
            $this->handleServiceRequestException('Google Translate', $e, $options['apiKey'], $logOptions, null, true);
            return null;
        } catch (GuzzleException $e) {
            $this->handleServiceGuzzleException('Google Translate', $e, $options['apiKey'], $logOptions, null, true);
            return null;
        }
    }

    public function streamProcess(string $prompt, array $options = []): \Generator
    {
        $result = $this->process($prompt, $options);
        if ($result === null) {
            yield '';
            return;
        }

        // Découpe le texte en phrases ou en morceaux de 50 caractères
        $chunkSize = 50;
        $length = strlen($result);
        for ($i = 0; $i < $length; $i += $chunkSize) {
            yield mb_substr($result, $i, $chunkSize);
            // Optionnel : flush pour forcer l’envoi
            if (ob_get_level() > 0) ob_flush();
            flush();
        }
    }
}