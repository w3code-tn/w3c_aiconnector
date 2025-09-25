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
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Localization\LanguageService;

class MistralService extends BaseService implements AiConnectorInterface
{
    private array $params = [];
    protected LoggerInterface $logger;
    protected LanguageServiceFactory $languageServiceFactory;
    protected ?LanguageService $languageService = null;

    protected int $retryCount = 0;

    public function __construct(
        LogManagerInterface $logManager,
        Context $context,
        LanguageServiceFactory $languageServiceFactory
    ) {
        $this->languageServiceFactory = $languageServiceFactory;
        $this->logger = $logManager->getLogger(static::class);
        $site = $GLOBALS['TYPO3_REQUEST']?->getAttribute('site');
        $currentLanguage = $site->getLanguageById($context->getAspect('language')->getId());
        $this->languageService = $this->languageServiceFactory->createFromSiteLanguage($currentLanguage);

        $extConf = GeneralUtility::makeInstance(ExtensionConfiguration::class)
            ->get('w3c_aiconnector');

        $this->params = [
            'apiKey' => $extConf['mistralApiKey'] ?? '',
            'model' => $extConf['mistralModelName'] ?? self::DEFAULT_MISTRAL_MODEL,
            'temperature' => (float)($extConf['mistralTemperature'] ?? self::DEFAULT_MISTRAL_TEMPERATURE),
            'topP' => (float)($extConf['mistralTopP'] ?? self::DEFAULT_MISTRAL_TOP_P),
            'maxTokens' => (int)($extConf['mistralMaxTokens'] ?? self::DEFAULT_MISTRAL_MAX_TOKENS),
            'stop' => $extConf['mistralStop'] ? GeneralUtility::trimExplode(',', $extConf['mistralStop'], true) : self::DEFAULT_MISTRAL_STOP,
            'randomSeed' => (int)($extConf['mistralRandomSeed'] ?? self::DEFAULT_MISTRAL_RANDOM_SEED),
            'stream' => (bool)($extConf['mistralStream'] ?? self::DEFAULT_MISTRAL_STREAM),
            'safePrompt' => (bool)($extConf['mistralSafePrompt'] ?? self::DEFAULT_MISTRAL_SAFE_PROMPT),
            'chunkSize' => (int)($extConf['mistralChunkSize'] ?? self::DEFAULT_STREAM_CHUNK_SIZE),
        ];
        $this->maxRetries = (int)($extConf['maxRetries'] ?? self::DEFAULT_MAX_RETRIES);
    }

    public function process(string $prompt, array $options = []): ?string
    {
        $options = $this->overrideParams($options,  $this->params);

        $logOptions = $options;
        if (isset($logOptions['apiKey'])) {
            $logOptions['apiKey'] = $this->maskApiKey($logOptions['apiKey']);
        }
        $this->logger->info('Mistral info: ', [
            'model' => $options['model'],
            'options' => $logOptions,
        ]);

        try {
            $client = new Client();
            $requestBody = [
                'model' => $options['model'],
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => $options['temperature'],
                'top_p' => $options['topP'],
                'max_tokens' => $options['maxTokens'],
                'random_seed' => $options['randomSeed'],
                'stream' => $options['stream'],
                'safe_prompt' => $options['safePrompt'],
            ];

            if (!empty($options['stop'])) {
                $requestBody['stop'] = $options['stop'];
            }

            $response = $client->post(self::DEFAULT_MISTRAL_API_ENDPOINT, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $options['apiKey'],
                    'Content-Type' => 'application/json'
                ],
                'json' => $requestBody
            ]);
            $body = json_decode((string)$response->getBody(), true);
            return $body['choices'][0]['message']['content'] ?? null;
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                if ( ($statusCode === 429) && $this->retryCount < $this->maxRetries) {
                    $this->retryCount++;
                    $this->logger->warning('Mistral 429 error', ['model' => $options['model'], 'options' => $logOptions]);
                    $options['model'] = $this->fallbackModel('mistral', $options['model']);
                    $this->process($prompt, $options);
                }
            }
            $this->handleServiceRequestException('Mistral', $e, $options['apiKey'], $logOptions, $options['model'], true, $this->logger);
            return '{error: "Mistral - ' . $this->languageService->sL('LLL:EXT:w3c_aiconnector/Resources/Private/Language/locallang.xlf:not_available') . '"}';
        } catch (GuzzleException $e) {
            $this->handleServiceGuzzleException('Mistral', $e, $options['apiKey'], $logOptions, $options['model'], true, $this->logger);
            return '{error: "Mistral - ' . $this->languageService->sL('LLL:EXT:w3c_aiconnector/Resources/Private/Language/locallang.xlf:not_available') . '"}';
        }
    }

    public function streamProcess(string $prompt, array $options = []): \Generator
    {
        $options = $this->overrideParams($options, $this->params);

        $logOptions = $options;
        if (isset($logOptions['apiKey'])) {
            $logOptions['apiKey'] = $this->maskApiKey($logOptions['apiKey']);
        }
        $this->logger->info('Mistral stream info: ', ['model' => $options['model'], 'options' => $logOptions]);

        $client = new Client();
        $requestBody = [
            'model' => $options['model'],
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => $options['temperature'],
            'top_p' => $options['topP'],
            'max_tokens' => $options['maxTokens'],
            'stream' => true, // Force streaming for this method
            'random_seed' => $options['randomSeed'],
            'safe_prompt' => $options['safePrompt'],
        ];

        if (!empty($options['stop'])) {
            $requestBody['stop'] = $options['stop'];
        }

        try {
            $response = $client->post(self::DEFAULT_MISTRAL_API_ENDPOINT, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $options['apiKey'],
                    'Content-Type' => 'application/json'
                ],
                'json' => $requestBody,
                'stream' => true,
            ]);

            $body = $response->getBody();
            while (!$body->eof()) {
                $line = $body->read(1024);
                if (str_starts_with($line, 'data: ')) {
                    $json = trim(substr($line, 5));
                    if ($json === '[DONE]') {
                        break;
                    }
                    $data = json_decode($json, true);
                    if (isset($data['choices'][0]['delta']['content'])) {
                        yield $data['choices'][0]['delta']['content'];
                    }
                }
            }
        } catch (RequestException $e) {
            $this->handleServiceRequestException('Mistral', $e, $options['apiKey'], $logOptions, $options['model'], true, $this->logger);
            yield 'Mistral - ' . $this->languageService->sL('LLL:EXT:w3c_aiconnector/Resources/Private/Language/locallang.xlf:not_available');
        } catch (GuzzleException $e) {
            $this->handleServiceGuzzleException('Mistral', $e, $options['apiKey'], $logOptions, $options['model'], true, $this->logger);
            yield 'Mistral - ' . $this->languageService->sL('LLL:EXT:w3c_aiconnector/Resources/Private/Language/locallang.xlf:not_available');
        }
    }
}