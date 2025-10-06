<?php

declare(strict_types=1);

namespace W3code\W3cAIConnector\Provider;

use Generator;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerAwareTrait;
use W3code\W3cAIConnector\Client\DeepLClient;
use W3code\W3cAIConnector\Utility\ConfigurationUtility;
use W3code\W3cAIConnector\Utility\LocalizationUtility;

class DeepLProvider extends AbstractProvider
{
    use LoggerAwareTrait;

    protected ?DeepLClient $client = null;

    /**
     * Set all configuration needed for the AI model provider
     *
     * @return void
     */
    public function setConfig(): void
    {
        $this->config = [
            'apiKey' => $this->extConfig['deeplApiKey'] ?? '',
            'target_lang' => ConfigurationUtility::getDefaultConfiguration('deeplTargetLang'),
            'source_lang' => $this->extConfig['deeplSourceLang']
                ?? ConfigurationUtility::getDefaultConfiguration('deeplSourceLang'),
            'split_sentences' => $this->extConfig['deeplSplitSentences']
                ?? ConfigurationUtility::getDefaultConfiguration('deeplSplitSentences'),
            'preserve_formatting' => (bool)($this->extConfig['deeplPreserveFormatting']
                ?? ConfigurationUtility::getDefaultConfiguration('deeplPreserveFormatting')),
            'formality' => $this->extConfig['deeplFormality']
                ?? ConfigurationUtility::getDefaultConfiguration('deeplFormality'),
            'glossary_id' => $this->extConfig['deeplGlossaryId']
                ?? ConfigurationUtility::getDefaultConfiguration('deeplGlossaryId'),
            'tag_handling' => $this->extConfig['deeplTagHandling']
                ?? ConfigurationUtility::getDefaultConfiguration('deeplTagHandling'),
            'outline_detection' => (bool)($this->extConfig['deeplOutlineDetection']
                ?? ConfigurationUtility::getDefaultConfiguration('deeplOutlineDetection')),
            'non_splitting_tags' => $this->extConfig['deeplNonSplittingTags']
                ?? ConfigurationUtility::getDefaultConfiguration('deeplNonSplittingTags'),
            'apiVersion' => $this->extConfig['deeplApiVersion'] ?? 'free',
            'maxRetries' => (int)($this->extConfig['maxRetries']
                ?? ConfigurationUtility::getDefaultConfiguration('maxRetries'))
        ];
    }

    /**
     * returns the response result from the api AI provider
     *
     * @param string $prompt
     * @param array $options
     * @param int $retryCount
     * @param bool $stream
     * @return string|Generator
     */
    public function process(string $prompt, array $options = [], int &$retryCount = 0, bool $stream = false): Generator|string
    {
        $options = $this->mergeConfigRecursive($options, $this->config);

        $logOptions = $options;
        unset($logOptions['api_key']);
        $this->logger->info('DeepL info: ', ['target_lang' => $options['target_lang'], 'options' => $logOptions]);

        try {
            $result = $this->client->getContent($prompt, $options, $stream);

            if($stream) {
                return $result;
            } else {
                if ($result === null) {
                    yield '';
                    return null;
                }

                if (str_starts_with($result, '{error:')) {
                    yield $result;
                    return null;
                }

                // Découpe le texte en phrases ou en morceaux de 50 caractères
                $chunkSize = 50;
                $length = strlen($result);
                for ($i = 0; $i < $length; $i += $chunkSize) {
                    yield mb_substr($result, $i, $chunkSize);
                    // Optionnel: flush pour forcer l’envoi
                    if (ob_get_level() > 0) ob_flush();
                    flush();
                }
            }
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                if (($statusCode === 429 || $statusCode === 456) && $retryCount < $this->config['maxRetries']) {
                    $retryCount++;
                    $this->logger->warning('DeepL' . $statusCode . 'error', ['options' => $logOptions]);
                    sleep(5); // Wait 5 seconds before retrying
                    return $this->process($prompt, $options);
                }
            }
            $this->handleServiceRequestException('DeepL', $e, $logOptions, $options['model']);
            return '{error: "DeepL - ' . LocalizationUtility::translate('not_available') . '"}';
        } catch (GuzzleException $e) {
            $this->handleServiceGuzzleException('DeepL', $e, $logOptions, $options['model']);
            return '{error: "DeepL - ' . LocalizationUtility::translate('not_available') . '"}';
        }
    }
}
