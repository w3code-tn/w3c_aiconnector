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

class OllamaService extends BaseService implements AiConnectorInterface
{
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
            'model' => $extConf['ollamaModelName'] ?? self::DEFAULT_OLLAMA_MODEL,
            'endPoint' => $extConf['ollamaEndPoint'] ?? self::DEFAULT_OLLAMA_API_ENDPOINT,
            'stream' => (bool)($extConf['ollamaStream'] ?? self::DEFAULT_OLLAMA_STREAM),
            'temperature' => (float)($extConf['ollamaTemperature'] ?? self::DEFAULT_OLLAMA_TEMPERATURE),
            'topP' => (float)($extConf['ollamaTopP'] ?? self::DEFAULT_OLLAMA_TOP_P),
            'numPredict' => (int)($extConf['ollamaNumPredict'] ?? self::DEFAULT_OLLAMA_NUM_PREDICT),
            'stop' => $extConf['ollamaStop'] ? GeneralUtility::trimExplode(',', $extConf['ollamaStop'], true) : self::DEFAULT_OLLAMA_STOP,
            'format' => $extConf['ollamaFormat'] ?? self::DEFAULT_OLLAMA_FORMAT,
            'system' => $extConf['ollamaSystem'] ?? self::DEFAULT_OLLAMA_SYSTEM,
            'chunkSize' => (int)($extConf['ollamaChunkSize'] ?? self::DEFAULT_STREAM_CHUNK_SIZE),
            'maxInputTokensAllowed' => (int)($extConf['ollamaMaxInputTokensAllowed'] ?? self::MAX_INPUT_TOKENS_ALLOWED),
        ];
        $this->maxRetries = (int)($extConf['maxRetries'] ?? self::DEFAULT_MAX_RETRIES);

        if (!empty($extConf['ollamaFallbackModels'])) {
            $this->fallbacks['ollama'] = $this->getExtConfFallbackModel($extConf['ollamaFallbackModels']);
        }
    }

    public function process(string $prompt, array $options = []): ?string
    {
        $options = $this->overrideParams($options, $this->params);

        $logOptions = $options;
        $this->logger->info('Ollama info: ', [ 'model' => $options['model'], 'options' => $logOptions ]);

        $client = new Client();

        try {
            $requestBody = [
                'model' => $options['model'],
                'prompt' => $prompt,
                'stream' => false,
            ];

            $ollamaOptions = [];
            if ($options['temperature'] !== self::DEFAULT_OLLAMA_TEMPERATURE) {
                $ollamaOptions['temperature'] = $options['temperature'];
            }
            if ($options['topP'] !== self::DEFAULT_OLLAMA_TOP_P) {
                $ollamaOptions['top_p'] = $options['topP'];
            }
            if ($options['numPredict'] !== self::DEFAULT_OLLAMA_NUM_PREDICT) {
                $ollamaOptions['num_predict'] = $options['numPredict'];
            }
            if (!empty($options['stop'])) {
                $ollamaOptions['stop'] = $options['stop'];
            }
            if (!empty($ollamaOptions)) {
                $requestBody['options'] = $ollamaOptions;
            }

            if (!empty($options['format'])) {
                $requestBody['format'] = $options['format'];
            }
            if (!empty($options['system'])) {
                $requestBody['system'] = $options['system'];
            }

            $response = $client->post($options['endPoint'] . '/api/generate', [
                'headers' => [
                    'Expect' => ''
                ],
                'json' => $requestBody,
                'timeout' => 300,
            ]);
            $body = json_decode((string)$response->getBody()->getContents(), true);

            return $body['response'] ?? null;

        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                if ( ($statusCode === 429 || $statusCode === 503) && $this->retryCount < $this->maxRetries) {
                    $this->retryCount++;
                    $this->logger->warning('Ollama 429 or 503 error', ['model' => $options['model'], 'options' => $logOptions]);
                    $options['model'] = $this->fallbackModel('ollama', $options['model']);
                    $this->process($prompt, $options);
                }
            }
            $this->handleServiceRequestException('Ollama', $e, '', $logOptions, $options['model'], true, $this->logger);
            return '{error: "Ollama - ' . $this->languageService->sL('LLL:EXT:w3c_aiconnector/Resources/Private/Language/locallang.xlf:not_available') . '"}';
        } catch (GuzzleException $e) {
            $this->handleServiceGuzzleException('Ollama', $e, '', $logOptions, $options['model'], true, $this->logger);
            return '{error: "Ollama - ' . $this->languageService->sL('LLL:EXT:w3c_aiconnector/Resources/Private/Language/locallang.xlf:not_available') . '"}';
        }
    }

    public function streamProcess(string $prompt, array $options = []): \Generator
    {
        $options = $this->overrideParams($options, $this->params);

        $logOptions = $options;
        $this->logger->info('Ollama stream info: ', ['model' => $options['model'], 'options' => $logOptions]);

        $client = new Client();
        $requestBody = [
            'model' => $options['model'],
            'prompt' => $prompt,
            'stream' => true, // Force streaming for this method
        ];

        $ollamaOptions = [];
        if ($options['temperature'] !== self::DEFAULT_OLLAMA_TEMPERATURE) {
            $ollamaOptions['temperature'] = $options['temperature'];
        }
        if ($options['topP'] !== self::DEFAULT_OLLAMA_TOP_P) {
            $ollamaOptions['top_p'] = $options['topP'];
        }
        if ($options['numPredict'] !== self::DEFAULT_OLLAMA_NUM_PREDICT) {
            $ollamaOptions['num_predict'] = $options['numPredict'];
        }
        if (!empty($options['stop'])) {
            $ollamaOptions['stop'] = $options['stop'];
        }
        if (!empty($ollamaOptions)) {
            $requestBody['options'] = $ollamaOptions;
        }

        if (!empty($options['format'])) {
            $requestBody['format'] = $options['format'];
        }
        if (!empty($options['system'])) {
            $requestBody['system'] = $options['system'];
        }

        try {
            $response = $client->post($options['endPoint'] . '/api/generate', [
                'json' => $requestBody,
                'timeout' => 300,
            ]);

            $body = $response->getBody();
            $buffer = '';
            while (!$body->eof()) {
                $buffer .= $body->read($options['chunkSize']); // Read a larger chunk into buffer
                while (($newlinePos = strpos($buffer, "\n")) !== false) {
                    $line = substr($buffer, 0, $newlinePos);
                    $buffer = substr($buffer, $newlinePos + 1); // Remove processed line from buffer

                    // Only process non-empty lines that might contain JSON
                    if (trim($line) !== '') {
                        $data = json_decode($line, true);
                        if (json_last_error() === JSON_ERROR_NONE && isset($data['response'])) {
                            yield $data['response'];
                        }
                    }
                }
            }
            // After loop, if there's any remaining buffer, try to process it as a final chunk
            if (trim($buffer) !== '') {
                $data = json_decode($buffer, true);
                $this->logger->info('Ollama stream final buffer: ' . $buffer, ['model' => $options['model'], 'options' => $logOptions]);
                if (json_last_error() === JSON_ERROR_NONE && isset($data['response'])) {
                    yield $data['response'];
                }
            }
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                if (($statusCode === 429 || $statusCode === 503) && !empty($this->fallbacks['ollama'])) {
                    $this->logger->warning('Ollama 429 or 503 error, trying fallback', ['model' => $options['model'], 'options' => $logOptions]);
                    //$options['model'] = $this->fallbackModel('ollama', $options['model']);
                    yield from $this->streamProcess($prompt, $options);
                    return;
                }
            }
            $this->handleServiceRequestException('Ollama', $e, '', $logOptions, $options['model'], true, $this->logger);
            yield 'Ollama - ' . $this->languageService->sL('LLL:EXT:w3c_aiconnector/Resources/Private/Language/locallang.xlf:not_available');
        } catch (GuzzleException $e) {
            $this->handleServiceGuzzleException('Ollama', $e, '', $logOptions, $options['model'], true, $this->logger);
            yield 'Ollama - ' . $this->languageService->sL('LLL:EXT:w3c_aiconnector/Resources/Private/Language/locallang.xlf:not_available');
        }
    }
}