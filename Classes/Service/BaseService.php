<?php

declare(strict_types=1);

namespace W3code\W3cAiconnector\Service;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

abstract class BaseService
{
    protected const DEFAULT_MISTRAL_API_ENDPOINT = 'https://api.mistral.ai/v1/chat/completions';
    protected const DEFAULT_CLAUDE_MODEL = 'claude-3-opus-20240229';
    protected const DEFAULT_COHERE_MODEL = 'command-r-plus';
    protected const DEFAULT_COHERE_MAX_TOKENS = 1024;
    protected const DEFAULT_COHERE_TEMPERATURE = 0.3;
    protected const DEFAULT_COHERE_P = 0.75;
    protected const DEFAULT_COHERE_K = 0;
    protected const DEFAULT_COHERE_FREQUENCY_PENALTY = 0.0;
    protected const DEFAULT_COHERE_PRESENCE_PENALTY = 0.0;
    protected const DEFAULT_COHERE_STOP_SEQUENCES = [];
    protected const DEFAULT_COHERE_STREAM = false;
    protected const DEFAULT_COHERE_PREAMBLE = '';
    protected const DEFAULT_GEMINI_MODEL = 'gemini-2.5-flash';
    protected const DEFAULT_MISTRAL_MODEL = 'mistral-large-latest';
    protected const DEFAULT_MISTRAL_TEMPERATURE = 0.7;
    protected const DEFAULT_MISTRAL_TOP_P = 1.0;
    protected const DEFAULT_MISTRAL_MAX_TOKENS = 1024;
    protected const DEFAULT_MISTRAL_STOP = [];
    protected const DEFAULT_MISTRAL_RANDOM_SEED = 0;
    protected const DEFAULT_MISTRAL_STREAM = false;
    protected const DEFAULT_MISTRAL_SAFE_PROMPT = false;
    protected const DEFAULT_OLLAMA_API_ENDPOINT = 'http://ollama:11434';
    protected const DEFAULT_OLLAMA_TEMPERATURE = 0.8;
    protected const DEFAULT_OLLAMA_TOP_P = 0.9;
    protected const DEFAULT_OLLAMA_NUM_PREDICT = 1024;
    protected const DEFAULT_OLLAMA_STOP = [];
    protected const DEFAULT_OLLAMA_FORMAT = ''; // e.g., 'json'
    protected const DEFAULT_OLLAMA_SYSTEM = '';
    protected const DEFAULT_OLLAMA_MODEL = 'llama3';
    protected const DEFAULT_OPENAI_API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';
    protected const DEFAULT_OPENAI_TEMPERATURE = 1.0;
    protected const DEFAULT_OPENAI_TOP_P = 1.0;
    protected const DEFAULT_OPENAI_MAX_TOKENS = 1024;
    protected const DEFAULT_OPENAI_STOP = [];
    protected const DEFAULT_OPENAI_STREAM = false;
    protected const DEFAULT_OPENAI_PRESENCE_PENALTY = 0.0;
    protected const DEFAULT_OPENAI_FREQUENCY_PENALTY = 0.0;
    protected const DEFAULT_OPENAI_MODEL = 'gpt-4o';
    protected const DEFAULT_CLAUDE_API_VERSION = '2023-06-01';
    protected const DEFAULT_CLAUDE_MAX_TOKENS = 1024;
    protected const DEFAULT_CLAUDE_SYSTEM = '';
    protected const DEFAULT_CLAUDE_STOP_SEQUENCES = [];
    protected const DEFAULT_CLAUDE_STREAM = false;
    protected const DEFAULT_CLAUDE_TEMPERATURE = 1.0;
    protected const DEFAULT_CLAUDE_TOP_P = 1.0;
    protected const DEFAULT_CLAUDE_TOP_K = 5;
    protected const DEFAULT_DEEPL_TARGET_LANG = 'EN-US';
    protected const DEFAULT_DEEPL_SOURCE_LANG = '';
    protected const DEFAULT_DEEPL_SPLIT_SENTENCES = 'on';
    protected const DEFAULT_DEEPL_PRESERVE_FORMATTING = false;
    protected const DEFAULT_DEEPL_FORMALITY = 'default';
    protected const DEFAULT_DEEPL_GLOSSARY_ID = '';
    protected const DEFAULT_DEEPL_TAG_HANDLING = '';
    protected const DEFAULT_DEEPL_OUTLINE_DETECTION = false;
    protected const DEFAULT_DEEPL_NON_SPLITTING_TAGS = '';
    protected const DEFAULT_GOOGLE_TRANSLATE_API_ENDPOINT = 'https://translation.googleapis.com/language/translate/v2';
    protected const DEFAULT_GOOGLE_TRANSLATE_TARGET_LANG = 'en';
    protected const DEFAULT_GOOGLE_TRANSLATE_SOURCE_LANG = '';
    protected const DEFAULT_GOOGLE_TRANSLATE_FORMAT = 'html';
    protected const DEFAULT_GOOGLE_TRANSLATE_MODEL = '';
    protected const DEFAULT_GOOGLE_TRANSLATE_CID = '';
    protected const DEFAULT_OLLAMA_STREAM = false;
    protected const DEFAULT_GEMINI_STREAM = false;
    protected const DEFAULT_GEMINI_TEMPERATURE = 0.9;
    protected const DEFAULT_GEMINI_TOP_P = 0.95;
    protected const DEFAULT_GEMINI_TOP_K = 40;
    protected const DEFAULT_GEMINI_CANDIDATE_COUNT = 1;
    protected const DEFAULT_GEMINI_MAX_OUTPUT_TOKENS = 1024;
    protected const DEFAULT_GEMINI_STOP_SEQUENCES = [];
    protected const DEFAULT_STREAM_CHUNK_SIZE = 50;
    protected const DEFAULT_MAX_RETRIES = 5;

    protected int $maxRetries;

    protected array $fallbacks = [
        'gemini' => [
            'gemini-2.5-flash' => 'gemini-2.0-flash',
            'gemini-2.0-flash' => 'gemini-1.5-flash',
            'gemini-1.5-flash' => 'gemini-2.5-flash',
        ],
        'openai' => [
            'gpt-4o' => 'gpt-4o-mini',
            'gpt-4o-mini' => 'gpt-3.5-turbo',
            'gpt-3.5-turbo' => 'gpt-4o',
        ],
        'claude' => [
            'claude-3-opus-20240229' => 'claude-2',
            'claude-2' => 'claude-instant-100k',
            'claude-instant-100k' => 'claude-3-opus-20240229',
        ],
        'cohere' => [
            'command-r-plus' => 'command-xlarge-nightly',
            'command-xlarge-nightly' => 'command-xlarge-20221108',
            'command-xlarge-20221108' => 'command-r-plus',
        ],
        'mistral' => [
            'mistral-large-latest' => 'mistral-small-latest',
            'mistral-small-latest' => 'mistral-large-latest',
        ],
        'ollama' => [
            'llama3' => 'llama2',
            'llama2' => 'llama1',
            'llama1' => 'llama3',
        ],
        // Add other services and their model fallbacks as needed
    ];

    protected function maskApiKey(string $apiKey): string
    {
        if (strlen($apiKey) <= 8) {
            return str_repeat('*', strlen($apiKey));
        }
        return substr($apiKey, 0, 4) . str_repeat('*', strlen($apiKey) - 8) . substr($apiKey, -4);
    }

    protected function overrideParams(array $options, array $params): array
    {
        foreach ($options as $key => $value) {
            if (array_key_exists($key, $params)) {
                $params[$key] = $value;
            } elseif (isset($params['generationConfig']) && array_key_exists($key, $params['generationConfig'])) {
                $params['generationConfig'][$key] = $value;
            }
        }
        return $params;
    }

    protected function maskErrorMessage(string $errorMessage, string $apiKey, bool $isUrlKey = true): string
    {
        if (empty($apiKey)) {
            return $errorMessage;
        }

        if ($isUrlKey) {
            // Mask the API key in the error message if it's present in a URL query parameter
            $errorMessage = preg_replace(
                '/\?key=' . preg_quote($apiKey, '/') . '(&|$)/',
                '?key=' . $this->maskApiKey($apiKey) . '$1',
                $errorMessage
            );
        } else {
            // Mask the API key as a general string replacement
            $errorMessage = str_replace($apiKey, $this->maskApiKey($apiKey), $errorMessage);
        }
        return $errorMessage;
    }

    protected function handleServiceRequestException(
        string $serviceName,
        RequestException $e,
        string $apiKey,
        array $logOptions,
        ?string $model,
        bool $isUrlKey = true,
        $logger = null
    ): void {
        $errorMessage = $e->getMessage();
        $responseBody = null;

        if ($e->hasResponse()) {
            $responseBody = (string) $e->getResponse()->getBody();
            // Attempt to decode as JSON
            $jsonError = json_decode($responseBody, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $responseBody = $jsonError; // Use decoded JSON
            }
        }

        // Mask the API key in the original error message
        $errorMessage = $this->maskErrorMessage($errorMessage, $apiKey, $isUrlKey);

        $logger->error(
            $serviceName . ' error: ',
            [
                'model' => $model,
                'options' => $logOptions,
                'response' => $responseBody ?? 'No response body available.'
            ]
        );
    }

    protected function handleServiceGuzzleException(
        string $serviceName,
        GuzzleException $e,
        string $apiKey,
        array $logOptions,
        ?string $model,
        bool $isUrlKey = true,
        $logger = null
    ): void {
        $errorMessage = $e->getMessage();
        // Mask the API key in the original error message
        $errorMessage = $this->maskErrorMessage($errorMessage, $apiKey, $isUrlKey);

        $logger->error(
            $serviceName . ' error: ',
            [
                'model' => $model,
                'options' => $logOptions,
                'response' => 'No response body available.'
            ]
        );
    }

    protected function fallbackModel(string $service, string $currentModel): string
    {
        return $this->fallbacks[$service][$currentModel];
    }
}
