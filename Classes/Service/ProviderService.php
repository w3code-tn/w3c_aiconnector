<?php
declare(strict_types=1);

namespace W3code\W3cAIConnector\Service;

use Exception;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use W3code\W3cAIConnector\Provider\ProviderInterface;
use W3code\W3cAIConnector\Utility\ConfigurationUtility;

/**
 * Class ProviderService
 */
class ProviderService
{

    /**
     * return the AI model provider name, e.x: gemini
     * NOTE: override this function in any other extension to add your custom provider
     *
     * @return string
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    public function getProvider(): string
    {
        $extConfig = ConfigurationUtility::getExtensionConfiguration();

        return $extConfig['provider'];
    }

    /**
     * initialize the Ai model provider instance.
     * @return ProviderInterface
     *
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws Exception
     */
    public function setProvider(): ProviderInterface
    {
        return $this->getProviderInstance($this->getProvider());
    }

    /**
     * returns the singleton of a provider identifier
     *
     * @param $providerIdentifier
     * @return ProviderInterface
     * @throws Exception
     */
    public function getProviderInstance($providerIdentifier): ProviderInterface
    {
        $className = $GLOBALS['TYPO3_CONF_VARS']['EXT']['w3c_aiconnector'][$providerIdentifier];

        if (empty($className)) {
            throw new Exception(
                sprintf('No provider instance found for the identifier "%s"', $providerIdentifier),
                1504017523
            );
        }

        return GeneralUtility::makeInstance($className);
    }

    /**
     * @param string $text
     * @return string
     */
    public function truncateGracefully(string $text): string
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
    protected function truncateSolrResults(string $basePrompt, array $results): array
    {
        $maxPromptLength = $this->provider->config['max_input_tokens_allowed'];
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
