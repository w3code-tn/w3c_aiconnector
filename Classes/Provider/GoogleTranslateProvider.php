<?php

declare(strict_types=1);

namespace W3code\W3cAIConnector\Provider;

use Generator;
use W3code\W3cAIConnector\Client\GoogleTranslateClient;

class GoogleTranslateProvider extends AbstractProvider
{
    private const PROVIDER_NAME = 'googleTranslate';
    protected ?GoogleTranslateClient $client = null;

    public function __construct()
    {
        parent::__construct();

        $this->client = new GoogleTranslateClient();
        $this->setup();
    }

    /**
     * Set all configuration needed for the AI model provider
     */
    public function setup(): void
    {
        $config = $this->extConfig[self::PROVIDER_NAME];
        $this->config = [
            'apiKey' => $config['apiKey'],
            'targetLang' => $config['targetLang'],
            'sourceLang' => $config['sourceLang'],
            'format' => $config['format'],
            'model' => $config['model'],
            'cid' => $config['cid'],
            'maxRetries' => (int)$config['maxRetries'],
        ];
    }

    /**
     * process the response from the AI provider
     *
     * @param string $prompt
     * @param array $options
     *
     * @return string
     */
    public function process(string $prompt, array $options = []): string
    {
        return $this->handleProcess(
            function ($prompt, $options) {
                $response = $this->client->generateResponse($prompt, $options);
                $body = json_decode((string)$response->getBody(), true);
                return $body['data']['translations'][0]['translatedText'] ?? null;
            },
            $prompt,
            self::PROVIDER_NAME,
            $options
        );
    }

    /**
     * process the response from the AI provider in streaming mode
     *
     * @param string $prompt
     * @param array $options
     * @return Generator
     */
    public function processStream(string $prompt, array $options = []): Generator
    {
        $result = $this->process($prompt, $options);
        if ($result === null) {
            yield '';
            return;
        }

        if (str_starts_with($result, '{error:')) {
            yield $result;
            return;
        }

        // Découpe le texte en phrases ou en morceaux de 50 caractères
        $chunkSize = 50;
        $length = strlen($result);
        for ($i = 0; $i < $length; $i += $chunkSize) {
            yield mb_substr($result, $i, $chunkSize);
            // Optionnel : flush pour forcer l’envoi
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
        }
    }

    /**
     * sets the log context
     */
    protected function setLogContext(array $options, array $logOptions): ?array
    {
        return [
            'targetLang' => $options['targetLang'],
            'options' => $logOptions,
        ];
    }
}
