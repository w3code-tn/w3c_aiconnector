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

class OllamaService extends BaseService implements AiConnectorInterface
{
    private array $params = [];
    protected LoggerInterface $logger;

    public function __construct(LogManagerInterface $logManager)
    {
        $this->logger = $logManager->getLogger(static::class);
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
        ];
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
                'stream' => $options['stream'],
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
                'json' => $requestBody
            ]);
            $body = json_decode((string)$response->getBody(), true);
            return $body['response'] ?? null;

        } catch (RequestException $e) {
            $this->handleServiceRequestException('Ollama', $e, '', $logOptions, $options['model'], false);
            return null;
        } catch (GuzzleException $e) {
            $this->handleServiceGuzzleException('Ollama', $e, '', $logOptions, $options['model'], false);
            return null;
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
                'stream' => true,
            ]);

            $body = $response->getBody();
            while (!$body->eof()) {
                $line = $body->read(1024);
                $data = json_decode($line, true);
                if (isset($data['response'])) {
                    yield $data['response'];
                }
            }
        } catch (RequestException $e) {
            $this->handleServiceRequestException('Ollama', $e, '', $logOptions, $options['model'], false, $this->logger);
        } catch (GuzzleException $e) {
            $this->handleServiceGuzzleException('Ollama', $e, '', $logOptions, $options['model'], false, $this->logger);
        }
    }
}