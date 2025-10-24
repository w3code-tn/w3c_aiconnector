<?php

declare(strict_types=1);

namespace W3code\W3cAIConnector\Provider;

use Generator;
use W3code\W3cAIConnector\Client\DeepLClient;

class DeepLProvider extends AbstractProvider
{
    private const PROVIDER_NAME = 'deepl';
    protected ?DeepLClient $client = null;

    public function __construct()
    {
        parent::__construct();

        $this->client = new DeepLClient();
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
            'target_lang' => $config['targetLang'],
            'source_lang' => $config['sourceLang'],
            'split_sentences' => $config['splitSentences'],
            'preserve_formatting' => (bool)$config['preserveFormatting'],
            'formality' => $config['formality'],
            'glossary_id' => $config['glossaryId'],
            'tag_handling' => $config['tagHandling'],
            'outline_detection' => (bool)$config['outlineDetection'],
            'non_splitting_tags' => $config['nonSplittingTags'],
            'apiVersion' => $config['apiVersion'],
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
                return $body['content'][0]['text'] ?? null;
            },
            $prompt,
            self::PROVIDER_NAME,
            $options,
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
            'target_lang' => $options['target_lang'],
            'options' => $logOptions
        ];
    }
}
