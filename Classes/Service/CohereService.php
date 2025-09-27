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

class CohereService extends BaseService implements AiConnectorInterface
{
    private const API_ENDPOINT = 'https://api.cohere.ai/v1/chat';
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
            'apiKey' => $extConf['cohereApiKey'] ?? '',
            'model' => $extConf['cohereModelName'] ?? self::DEFAULT_COHERE_MODEL,
            'maxTokens' => $extConf['cohereMaxTokens'] ?? self::DEFAULT_COHERE_MAX_TOKENS,
            'temperature' => (float)($extConf['cohereTemperature'] ?? self::DEFAULT_COHERE_TEMPERATURE),
            'p' => (float)($extConf['cohereP'] ?? self::DEFAULT_COHERE_P),
            'k' => (int)($extConf['cohereK'] ?? self::DEFAULT_COHERE_K),
            'frequencyPenalty' => (float)($extConf['cohereFrequencyPenalty'] ?? self::DEFAULT_COHERE_FREQUENCY_PENALTY),
            'presencePenalty' => (float)($extConf['coherePresencePenalty'] ?? self::DEFAULT_COHERE_PRESENCE_PENALTY),
            'stopSequences' => $extConf['cohereStopSequences'] ? explode(',', $extConf['cohereStopSequences']) : self::DEFAULT_COHERE_STOP_SEQUENCES,
            'stream' => (bool)($extConf['cohereStream'] ?? self::DEFAULT_COHERE_STREAM),
            'preamble' => $extConf['coherePreamble'] ?? self::DEFAULT_COHERE_PREAMBLE,
            'chatHistory' => [], // Cohere expects an array of messages for chat_history
            'chunkSize' => (int)($extConf['cohereChunkSize'] ?? self::DEFAULT_STREAM_CHUNK_SIZE),
        ];
        $this->maxRetries = (int)($extConf['maxRetries'] ?? self::DEFAULT_MAX_RETRIES);

        if (!empty($extConf['cohereFallbackModels'])) {
            $this->fallbacks['cohere'] = $this->getExtConfFallbackModel($extConf['cohereFallbackModels']);
        }
    }

    public function process(string $prompt, array $options = []): ?string
    {
        $options = $this->overrideParams($options, $this->params);

        $logOptions = $options;
        if (isset($logOptions['apiKey'])) {
            $logOptions['apiKey'] = $this->maskApiKey($logOptions['apiKey']);
        }
        $this->logger->info('Cohere info: ', ['model' => $options['model'], 'options' => $logOptions]);

        $jsonBody = [
            'model' => $options['model'],
            'message' => $prompt, // The user's message to send to the model. Can be used instead of chat_history
        ];

        if (!empty($options['chatHistory'])) {
            $jsonBody['chat_history'] = $options['chatHistory'];
        }
        if (!empty($options['maxTokens'])) {
            $jsonBody['max_tokens'] = $options['maxTokens'];
        }
        if (isset($options['temperature'])) {
            $jsonBody['temperature'] = $options['temperature'];
        }
        if (isset($options['p'])) {
            $jsonBody['p'] = $options['p'];
        }
        if (isset($options['k'])) {
            $jsonBody['k'] = $options['k'];
        }
        if (isset($options['frequencyPenalty'])) {
            $jsonBody['frequency_penalty'] = $options['frequencyPenalty'];
        }
        if (isset($options['presencePenalty'])) {
            $jsonBody['presence_penalty'] = $options['presencePenalty'];
        }
        if (!empty($options['stopSequences'])) {
            $jsonBody['stop_sequences'] = $options['stopSequences'];
        }
        if (isset($options['stream'])) {
            $jsonBody['stream'] = $options['stream'];
        }
        if (!empty($options['preamble'])) {
            $jsonBody['preamble'] = $options['preamble'];
        }

        try {
            $client = new Client();
            $response = $client->post(self::API_ENDPOINT, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $options['apiKey'],
                    'Content-Type' => 'application/json'
                ],
                'json' => $jsonBody
            ]);
            $body = json_decode((string)$response->getBody(), true);
            return $body['text'] ?? null;
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                if ( ($statusCode === 429) && $this->retryCount < $this->maxRetries) {
                    $this->retryCount++;
                    $this->logger->warning('Cohere 429 error', ['model' => $options['model'], 'options' => $logOptions]);
                    $options['model'] = $this->fallbackModel('cohere', $options['model']);
                    $this->process($prompt, $options);
                }
            }
            $this->handleServiceRequestException('Cohere', $e, $options['apiKey'], $logOptions, $options['model'], true, $this->logger);
            return '{error: "Cohere - ' . $this->languageService->sL('LLL:EXT:w3c_aiconnector/Resources/Private/Language/locallang.xlf:not_available') . '"}';
        } catch (GuzzleException $e) {
            $this->handleServiceGuzzleException('Cohere', $e, $options['apiKey'], $logOptions, $options['model'], false, $this->logger);
            return '{error: "Cohere - ' . $this->languageService->sL('LLL:EXT:w3c_aiconnector/Resources/Private/Language/locallang.xlf:not_available') . '"}';
        }
    }

    public function streamProcess(string $prompt, array $options = []): \Generator
    {
        $options = $this->overrideParams($options, $this->params);

        $logOptions = $options;
        if (isset($logOptions['apiKey'])) {
            $logOptions['apiKey'] = $this->maskApiKey($logOptions['apiKey']);
        }
        $this->logger->info('Cohere stream info: ', ['model' => $options['model'], 'options' => $logOptions]);

        $client = new Client();
        $jsonBody = [
            'model' => $options['model'],
            'message' => $prompt,
            'stream' => true, // Force streaming for this method
        ];

        if (!empty($options['chatHistory'])) {
            $jsonBody['chat_history'] = $options['chatHistory'];
        }
        if (!empty($options['maxTokens'])) {
            $jsonBody['max_tokens'] = $options['maxTokens'];
        }
        if (isset($options['temperature'])) {
            $jsonBody['temperature'] = $options['temperature'];
        }
        if (isset($options['p'])) {
            $jsonBody['p'] = $options['p'];
        }
        if (isset($options['k'])) {
            $jsonBody['k'] = $options['k'];
        }
        if (isset($options['frequencyPenalty'])) {
            $jsonBody['frequency_penalty'] = $options['frequencyPenalty'];
        }
        if (isset($options['presencePenalty'])) {
            $jsonBody['presence_penalty'] = $options['presencePenalty'];
        }
        if (!empty($options['stopSequences'])) {
            $jsonBody['stop_sequences'] = $options['stopSequences'];
        }
        if (!empty($options['preamble'])) {
            $jsonBody['preamble'] = $options['preamble'];
        }

        try {
            $response = $client->post(self::API_ENDPOINT, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $options['apiKey'],
                    'Content-Type' => 'application/json'
                ],
                'json' => $jsonBody,
                'stream' => true,
            ]);

            $body = $response->getBody();
            while (!$body->eof()) {
                $line = $body->read(1024);
                $data = json_decode($line, true);
                if (isset($data['event_type']) && $data['event_type'] === 'text-generation' && isset($data['text'])) {
                    yield $data['text'];
                }
            }
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                if (($statusCode === 429 || $statusCode === 503) && !empty($this->fallbacks['cohere'])) {
                    $this->logger->warning('Cohere 429 or 503 error, trying fallback', ['model' => $options['model'], 'options' => $logOptions]);
                    $options['model'] = $this->fallbackModel('cohere', $options['model']);
                    yield from $this->streamProcess($prompt, $options);
                    return;
                }
            }
            $this->handleServiceRequestException('Cohere', $e, $options['apiKey'], $logOptions, $options['model'], false, $this->logger);
            yield 'Cohere - ' . $this->languageService->sL('LLL:EXT:w3c_aiconnector/Resources/Private/Language/locallang.xlf:not_available');
        } catch (GuzzleException $e) {
            $this->handleServiceGuzzleException('Cohere', $e, $options['apiKey'], $logOptions, $options['model'], false, $this->logger);
            yield 'Cohere - ' . $this->languageService->sL('LLL:EXT:w3c_aiconnector/Resources/Private/Language/locallang.xlf:not_available');
        }
    }
}