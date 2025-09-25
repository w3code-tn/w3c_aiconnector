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

class ClaudeService extends BaseService implements AiConnectorInterface
{
    private const API_ENDPOINT = 'https://api.anthropic.com/v1/messages';
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
            'apiKey' => $extConf['claudeApiKey'] ?? '',
            'model' => $extConf['claudeModelName'] ?? self::DEFAULT_CLAUDE_MODEL,
            'apiVersion' => $extConf['claudeApiVersion'] ?? self::DEFAULT_CLAUDE_API_VERSION,
            'maxTokens' => $extConf['claudeMaxTokens'] ?? self::DEFAULT_CLAUDE_MAX_TOKENS,
            'system' => $extConf['claudeSystem'] ?? self::DEFAULT_CLAUDE_SYSTEM,
            'stopSequences' => $extConf['claudeStopSequences'] ? explode(',', $extConf['claudeStopSequences']) : self::DEFAULT_CLAUDE_STOP_SEQUENCES,
            'stream' => (bool)($extConf['claudeStream'] ?? self::DEFAULT_CLAUDE_STREAM),
            'temperature' => (float)($extConf['claudeTemperature'] ?? self::DEFAULT_CLAUDE_TEMPERATURE),
            'topP' => (float)($extConf['claudeTopP'] ?? self::DEFAULT_CLAUDE_TOP_P),
            'topK' => (int)($extConf['claudeTopK'] ?? self::DEFAULT_CLAUDE_TOP_K),
            'chunkSize' => (int)($extConf['claudeChunkSize'] ?? self::DEFAULT_STREAM_CHUNK_SIZE),
        ];
        $this->maxRetries = (int)($extConf['maxRetries'] ?? self::DEFAULT_MAX_RETRIES);
    }

    public function process(string $prompt, array $options = []): ?string
    {
        $options = $this->overrideParams($options, $this->params);

        $logOptions = $options;
        if (isset($logOptions['apiKey'])) {
            $logOptions['apiKey'] = $this->maskApiKey($logOptions['apiKey']);
        }
        $this->logger->info('Claude info: ', ['model' => $options['model'], 'options' => $logOptions]);

        $jsonBody = [
            'model' => $options['model'],
            'max_tokens' => $options['maxTokens'],
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ]
        ];

        if (!empty($options['system'])) {
            $jsonBody['system'] = $options['system'];
        }
        if (!empty($options['stopSequences'])) {
            $jsonBody['stop_sequences'] = $options['stopSequences'];
        }
        if (isset($options['temperature'])) {
            $jsonBody['temperature'] = $options['temperature'];
        }
        if (isset($options['topP'])) {
            $jsonBody['top_p'] = $options['topP'];
        }
        if (isset($options['topK'])) {
            $jsonBody['top_k'] = $options['topK'];
        }

        try {
            $client = new Client();
            $response = $client->post(self::API_ENDPOINT, [
                'headers' => [
                    'x-api-key' => $options['apiKey'],
                    'anthropic-version' => $options['apiVersion'],
                    'Content-Type' => 'application/json'
                ],
                'json' => $jsonBody
            ]);
            $body = json_decode((string)$response->getBody(), true);
            // Adapte la clé selon la réponse Claude
            return $body['content'][0]['text'] ?? null;
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                if ( ($statusCode === 429 || $statusCode === 529 ) && $this->retryCount < $this->maxRetries) {
                    $this->retryCount++;
                    $this->logger->warning('Claude 429 or 529 error', ['model' => $options['model'], 'options' => $logOptions]);
                    $options['model'] = $this->fallbackModel('claude', $options['model']);
                    $this->process($prompt, $options);
                }
            }
            $this->handleServiceRequestException('Claude', $e, $options['apiKey'], $logOptions, $options['model'], true, $this->logger);
            return '{error: "Claude - ' . $this->languageService->sL('LLL:EXT:w3c_aiconnector/Resources/Private/Language/locallang.xlf:not_available') . '"}';
        } catch (GuzzleException $e) {
            $this->handleServiceGuzzleException('Claude', $e, $options['apiKey'], $logOptions, $options['model'], true, $this->logger);
            return '{error: "Claude - ' . $this->languageService->sL('LLL:EXT:w3c_aiconnector/Resources/Private/Language/locallang.xlf:not_available') . '"}';
        }
    }

    public function streamProcess(string $prompt, array $options = []): \Generator
    {
        $options = $this->overrideParams($options, $this->params);

        $logOptions = $options;
        if (isset($logOptions['apiKey'])) {
            $logOptions['apiKey'] = $this->maskApiKey($logOptions['apiKey']);
        }
        $this->logger->info('Claude stream info: ', ['model' => $options['model'], 'options' => $logOptions]);

        $client = new Client();
        $jsonBody = [
            'model' => $options['model'],
            'max_tokens' => $options['maxTokens'],
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'stream' => true, // Force streaming for this method
        ];

        if (!empty($options['system'])) {
            $jsonBody['system'] = $options['system'];
        }
        if (!empty($options['stopSequences'])) {
            $jsonBody['stop_sequences'] = $options['stopSequences'];
        }
        if (isset($options['temperature'])) {
            $jsonBody['temperature'] = $options['temperature'];
        }
        if (isset($options['topP'])) {
            $jsonBody['top_p'] = $options['topP'];
        }
        if (isset($options['topK'])) {
            $jsonBody['top_k'] = $options['topK'];
        }

        try {
            $response = $client->post(self::API_ENDPOINT, [
                'headers' => [
                    'x-api-key' => $options['apiKey'],
                    'anthropic-version' => $options['apiVersion'],
                    'Content-Type' => 'application/json'
                ],
                'json' => $jsonBody,
                'stream' => true,
            ]);

            $body = $response->getBody();
            $buffer = '';
            while (!$body->eof()) {
                $buffer .= $body->read(1024);
                while (($pos = strpos($buffer, "\n\n")) !== false) {
                    $eventData = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 2);

                    $event = null;
                    $data = null;

                    foreach (explode("\n", $eventData) as $line) {
                        if (str_starts_with($line, 'event: ')) {
                            $event = trim(substr($line, 7));
                        } elseif (str_starts_with($line, 'data: ')) {
                            $data = trim(substr($line, 6));
                        }
                    }

                    if ($event === 'content_block_delta') {
                        $json = json_decode($data, true);
                        if (isset($json['delta']['text'])) {
                            yield $json['delta']['text'];
                        }
                    }
                }
            }
        } catch (RequestException $e) {
            $this->handleServiceRequestException('Claude', $e, $options['apiKey'], $logOptions, $options['model'], true, $this->logger);
            yield 'Claude - ' . $this->languageService->sL('LLL:EXT:w3c_aiconnector/Resources/Private/Language/locallang.xlf:not_available');
        } catch (GuzzleException $e) {
            $this->handleServiceGuzzleException('Claude', $e, $options['apiKey'], $logOptions, $options['model'], true, $this->logger);
            yield 'Claude - ' . $this->languageService->sL('LLL:EXT:w3c_aiconnector/Resources/Private/Language/locallang.xlf:not_available');
        }
    }
}