<?php

declare(strict_types=1);

namespace W3code\W3cAiconnector\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use W3code\W3cAiconnector\Interface\AiConnectorInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class OllamaService extends BaseService implements AiConnectorInterface
{
    public function process(string $prompt, array $options = []): ?string
    {
        $extConf = GeneralUtility::makeInstance(ExtensionConfiguration::class)
            ->get('w3c_aiconnector');

        $model = $options['model'] ?? $extConf['ollamaModelName'] ?? self::DEFAULT_OLLAMA_MODEL;
        $endPoint = $options['endPoint'] ?? $extConf['ollamaEndPoint'] ?? self::DEFAULT_OLLAMA_API_ENDPOINT;
        $stream = (bool)($options['stream'] ?? $extConf['ollamaStream'] ?? self::DEFAULT_OLLAMA_STREAM);
        $temperature = (float)($options['temperature'] ?? $extConf['ollamaTemperature'] ?? self::DEFAULT_OLLAMA_TEMPERATURE);
        $topP = (float)($options['topP'] ?? $extConf['ollamaTopP'] ?? self::DEFAULT_OLLAMA_TOP_P);
        $numPredict = (int)($options['numPredict'] ?? $extConf['ollamaNumPredict'] ?? self::DEFAULT_OLLAMA_NUM_PREDICT);
        $stop = $options['stop'] ?? ($extConf['ollamaStop'] ? GeneralUtility::trimExplode(',', $extConf['ollamaStop'], true) : self::DEFAULT_OLLAMA_STOP);
        $format = $options['format'] ?? $extConf['ollamaFormat'] ?? self::DEFAULT_OLLAMA_FORMAT;
        $system = $options['system'] ?? $extConf['ollamaSystem'] ?? self::DEFAULT_OLLAMA_SYSTEM;

        $logOptions = $options;
        $this->logger->info('Ollama info: ', [ 'model' => $model, 'options' => $logOptions ]);

        $client = new Client();

        try {
            $requestBody = [
                'model' => $model,
                'prompt' => $prompt,
                'stream' => $stream,
            ];

            $ollamaOptions = [];
            if ($temperature !== self::DEFAULT_OLLAMA_TEMPERATURE) {
                $ollamaOptions['temperature'] = $temperature;
            }
            if ($topP !== self::DEFAULT_OLLAMA_TOP_P) {
                $ollamaOptions['top_p'] = $topP;
            }
            if ($numPredict !== self::DEFAULT_OLLAMA_NUM_PREDICT) {
                $ollamaOptions['num_predict'] = $numPredict;
            }
            if (!empty($stop)) {
                $ollamaOptions['stop'] = $stop;
            }
            if (!empty($ollamaOptions)) {
                $requestBody['options'] = $ollamaOptions;
            }

            if (!empty($format)) {
                $requestBody['format'] = $format;
            }
            if (!empty($system)) {
                $requestBody['system'] = $system;
            }

            $response = $client->post($endPoint . '/api/generate', [
                'json' => $requestBody
            ]);
            $body = json_decode((string)$response->getBody(), true);
            return $body['response'] ?? null;

        } catch (RequestException $e) {
            $this->handleServiceRequestException('Ollama', $e, '', $logOptions, $model, false);
            return null;
        } catch (GuzzleException $e) {
            $this->handleServiceGuzzleException('Ollama', $e, '', $logOptions, $model, false);
            return null;
        }
    }
}