<?php
declare(strict_types=1);

namespace W3code\W3cAIConnector\Utility;

use TYPO3\CMS\Core\Utility\StringUtility;

/**
 * Class ProviderUtility
 */
class ProviderUtility
{

    /**
     * mask the api key for logging purposes
     *
     * @param string $apiKey
     */
    public static function maskApiKey(string $apiKey): string
    {
        if (strlen($apiKey) <= 8) {
            return str_repeat('*', strlen($apiKey));
        }
        return substr($apiKey, 0, 4) . str_repeat('*', strlen($apiKey) - 8) . substr($apiKey, -4);
    }

    /**
     * @param string $text
     * @return string
     */
    public static function truncateGracefully(string $text): string
    {
        if (empty($text)) {
            return '';
        }
        if (!str_ends_with($text, "\n")) {
            $lastNewlinePos = strrpos($text, "\n");
            if ($lastNewlinePos !== false) {
                return substr($text, 0, $lastNewlinePos);
            }
        }
        return $text;
    }

    /**
     * @param string $basePrompt
     * @param array $results
     * @return array
     */
    public static function truncateSolrResults(array $config, string $basePrompt, array $results): array
    {
        $maxPromptLength = $config['max_input_tokens_allowed'];
        $resultStrings = [];
        foreach ($results as $result) {
            $resultStrings[] = "- titre: " . ($result['title'] ?? '') . " - contenu: " . ($result['content'] ?? '') . " - URL: " . ($result['url'] ?? '') . "\n";
        }

        $prompt = $basePrompt . implode('', $resultStrings);

        while (strlen($prompt) > $maxPromptLength && count($results) > 1) {
            array_pop($results);
            array_pop($resultStrings);
            $prompt = $basePrompt . implode('', $resultStrings);
        }

        return $results;
    }
}
