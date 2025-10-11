<?php
declare(strict_types=1);

namespace W3code\W3cAiconnector\Service;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

abstract class AbstractAiService
{
    protected AiConnectorFactory $aiConnectorFactory;
    protected LanguageServiceFactory $languageServiceFactory;
    protected SearchServiceFactory $searchServiceFactory;
    protected ?LanguageService $languageService = null;
    protected $aiConnector = null;

    public function __construct(
        AiConnectorFactory $aiConnectorFactory,
        LanguageServiceFactory $languageServiceFactory,
        SearchServiceFactory $searchServiceFactory,
        ?string $provider = null
    ) {
        $this->aiConnectorFactory = $aiConnectorFactory;
        $this->languageServiceFactory = $languageServiceFactory;
        $this->searchServiceFactory = $searchServiceFactory;
        $this->setAiConnector($provider);
    }

    abstract protected function getExtensionKey(): string;

    protected function setAiConnector(?string $provider = null): void
    {
        if (!$provider) {
            $extConfAiConnector = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('w3c_aiconnector');
            $extConf = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get($this->getExtensionKey());
            $provider = $extConf['provider'] ?? $extConfAiConnector['provider'] ?? '';
        }
        $this->aiConnector = $this->aiConnectorFactory->create($provider);
    }

    public function getSearchService(string $serviceName): ?object
    {
        return $this->searchServiceFactory->create($serviceName);
    }

    public function truncateGracefully(string $text): string
    {
        if (empty($text)) {
            return '';
        }
        if (substr($text, -1) !== "\n") {
            $lastNewlinePos = strrpos($text, "\n");
            if ($lastNewlinePos !== false) {
                return substr($text, 0, $lastNewlinePos);
            }
        }
        return $text;
    }

    protected function truncateSearchResultsIfNeeded(string $basePrompt, array $results): array
    {
        $maxPromptLength = $this->aiConnector->getParams()['maxInputTokensAllowed'];
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
