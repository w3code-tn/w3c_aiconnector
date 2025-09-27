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

class OpenAiService extends BaseService implements AiConnectorInterface
{
    private array $params = [];
    protected LoggerInterface $logger;
    protected LanguageServiceFactory $languageServiceFactory;
    protected ?LanguageService $languageService = null;

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
            'apiKey' => $extConf['openAiApiKey'] ?? '',
            'model' => $extConf['openAiModelName'] ?? self::DEFAULT_OPENAI_MODEL,
            'temperature' => (float)($extConf['openAiTemperature'] ?? self::DEFAULT_OPENAI_TEMPERATURE),
            'topP' => (float)($extConf['openAiTopP'] ?? self::DEFAULT_OPENAI_TOP_P),
            'maxTokens' => (int)($extConf['openAiMaxTokens'] ?? self::DEFAULT_OPENAI_MAX_TOKENS),
            'stop' => $extConf['openAiStop'] ? GeneralUtility::trimExplode(',', $extConf['openAiStop'], true) : self::DEFAULT_OPENAI_STOP,
            'stream' => (bool)($extConf['openAiStream'] ?? self::DEFAULT_OPENAI_STREAM),
            'presencePenalty' => (float)($extConf['openAiPresencePenalty'] ?? self::DEFAULT_OPENAI_PRESENCE_PENALTY),
            'frequencyPenalty' => (float)($extConf['openAiFrequencyPenalty'] ?? self::DEFAULT_OPENAI_FREQUENCY_PENALTY),
            'chunkSize' => (int)($extConf['openAiChunkSize'] ?? self::DEFAULT_STREAM_CHUNK_SIZE),
        ];

        if (!empty($extConf['openAiFallbackModels'])) {
            $this->fallbacks['openai'] = $this->getExtConfFallbackModel($extConf['openAiFallbackModels']);
        }
    }

    public function process(string $prompt, array $options = []): ?string
    {
        $options = $this->overrideParams($options, $this->params);

        $logOptions = $options;
        if (isset($logOptions['apiKey'])) {
            $logOptions['apiKey'] = $this->maskApiKey($logOptions['apiKey']);
        }
        $this->logger->info('OpenAI info: ', [
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
                'stream' => $options['stream'],
                'presence_penalty' => $options['presencePenalty'],
                'frequency_penalty' => $options['frequencyPenalty'],
            ];

            if (!empty($options['stop'])) {
                $requestBody['stop'] = $options['stop'];
            }

            $response = $client->post(self::DEFAULT_OPENAI_API_ENDPOINT, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $options['apiKey'],
                    'Content-Type' => 'application/json'
                ],
                'json' => $requestBody
            ]);
            $body = json_decode((string)$response->getBody(), true);
            return $body['choices'][0]['message']['content'] ?? null;
        } catch (RequestException $e) {
            $this->handleServiceRequestException('OpenAI', $e, $options['apiKey'], $logOptions, $options['model'], true, $this->logger);
            return '{error: "OpenAI - ' . $this->languageService->sL('LLL:EXT:w3c_aiconnector/Resources/Private/Language/locallang.xlf:not_available') . '"}';
        } catch (GuzzleException $e) {
            $this->handleServiceGuzzleException('OpenAI', $e, $options['apiKey'], $logOptions, $options['model'], true, $this->logger);
            return '{error: "OpenAI - ' . $this->languageService->sL('LLL:EXT:w3c_aiconnector/Resources/Private/Language/locallang.xlf:not_available') . '"}';
        }
    }

    public function streamProcess(string $prompt, array $options = []): \Generator
    {
        $options = $this->overrideParams($options, $this->params);

        $logOptions = $options;
        if (isset($logOptions['apiKey'])) {
            $logOptions['apiKey'] = $this->maskApiKey($logOptions['apiKey']);
        }
        $this->logger->info('OpenAI stream info: ', ['model' => $options['model'], 'options' => $logOptions]);

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
            'presence_penalty' => $options['presencePenalty'],
            'frequency_penalty' => $options['frequencyPenalty'],
        ];

        if (!empty($options['stop'])) {
            $requestBody['stop'] = $options['stop'];
        }

        try {
            $response = $client->post(self::DEFAULT_OPENAI_API_ENDPOINT, [
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
            $this->handleServiceRequestException('OpenAI', $e, $options['apiKey'], $logOptions, $options['model'], true, $this->logger);
            yield 'OpenAI - ' . $this->languageService->sL('LLL:EXT:w3c_aiconnector/Resources/Private/Language/locallang.xlf:not_available');
        } catch (GuzzleException $e) {
            $this->handleServiceGuzzleException('OpenAI', $e, $options['apiKey'], $logOptions, $options['model'], true, $this->logger);
            yield 'OpenAI - ' . $this->languageService->sL('LLL:EXT:w3c_aiconnector/Resources/Private/Language/locallang.xlf:not_available');
        }
    }
}