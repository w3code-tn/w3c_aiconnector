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
            'apiKey' => $extConf['openAiApiKey'] ?? '',
            'model' => $extConf['openAiModelName'] ?? self::DEFAULT_OPENAI_MODEL,
            'temperature' => (float)($extConf['openAiTemperature'] ?? self::DEFAULT_OPENAI_TEMPERATURE),
            'topP' => (float)($extConf['openAiTopP'] ?? self::DEFAULT_OPENAI_TOP_P),
            'max_output_tokens' => (int)($extConf['openAiMaxOutputTokens'] ?? self::DEFAULT_OPENAI_MAX_TOKENS),
            'stop' => $extConf['openAiStop'] ? GeneralUtility::trimExplode(',', $extConf['openAiStop'], true) : self::DEFAULT_OPENAI_STOP,
            'stream' => (bool)($extConf['openAiStream'] ?? self::DEFAULT_OPENAI_STREAM),
            'chunkSize' => (int)($extConf['openAiChunkSize'] ?? self::DEFAULT_STREAM_CHUNK_SIZE),
        ];
        $this->maxRetries = (int)($extConf['maxRetries'] ?? self::DEFAULT_MAX_RETRIES);

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
                'input' => $prompt,
                'temperature' => $options['temperature'],
                'top_p' => $options['topP'],
                'max_output_tokens' => $options['max_output_tokens'],
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
            return $body['output'][0]['content'][0]['text'] ?? null;
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                if ( ($statusCode === 429) && $this->retryCount < $this->maxRetries) {
                    $this->retryCount++;
                    $this->logger->warning('OpenAI 429 error', ['model' => $options['model'], 'options' => $logOptions]);
                    $this->handleServiceRequestException('OpenAI', $e, $options['apiKey'], $logOptions, $options['model'], false, $this->logger);
                    $options['model'] = $this->fallbackModel('openai', $options['model']);
                    return $this->process($prompt, $options);
                }
            }
            $this->handleServiceRequestException('OpenAI', $e, $options['apiKey'], $logOptions, $options['model'], false, $this->logger);
            return '{error: "OpenAI - ' . $this->languageService->sL('LLL:EXT:w3c_aiconnector/Resources/Private/Language/locallang.xlf:not_available') . '"}';
        }
        catch (GuzzleException $e) {
            $this->handleServiceGuzzleException('OpenAI', $e, $options['apiKey'], $logOptions, $options['model'], false, $this->logger);
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
            'input' => $prompt,
            'temperature' => $options['temperature'],
            'top_p' => $options['topP'],
            'max_output_tokens' => $options['max_output_tokens'],
            'stream' => true, // Force streaming for this method
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
                    if (isset($data['output'][0]['content'][0]['text'])) {
                        yield $data['output'][0]['content'][0]['text'];
                    }
                }
            }
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                if (($statusCode === 429) && $this->retryCount < $this->maxRetries && !empty($this->fallbacks['openai'])) {
                    $this->retryCount++;
                    $this->logger->warning('OpenAI 429 error, trying fallback', ['model' => $options['model'], 'options' => $logOptions]);
                    $options['model'] = $this->fallbackModel('openai', $options['model']);
                    yield from $this->streamProcess($prompt, $options);
                    return;
                }
            }
            $this->handleServiceRequestException('OpenAI', $e, $options['apiKey'], $logOptions, $options['model'], false, $this->logger);
            yield 'OpenAI - ' . $this->languageService->sL('LLL:EXT:w3c_aiconnector/Resources/Private/Language/locallang.xlf:not_available');
        } catch (GuzzleException $e) {
            $this->handleServiceGuzzleException('OpenAI', $e, $options['apiKey'], $logOptions, $options['model'], false, $this->logger);
            yield 'OpenAI - ' . $this->languageService->sL('LLL:EXT:w3c_aiconnector/Resources/Private/Language/locallang.xlf:not_available');
        }
    }
}