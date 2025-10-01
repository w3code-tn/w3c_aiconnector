<?php
declare(strict_types=1);

namespace W3code\W3cAiconnector\Service;

use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

abstract class AbstractAiService
{
    protected AiConnectorFactory $aiConnectorFactory;
    protected LanguageServiceFactory $languageServiceFactory;
    protected SolrService $solrService;
    protected ?LanguageService $languageService = null;
    protected $aiConnector = null;

    public function __construct(
        AiConnectorFactory $aiConnectorFactory,
        LanguageServiceFactory $languageServiceFactory,
        SolrService $solrService
    ) {
        $this->aiConnectorFactory = $aiConnectorFactory;
        $this->languageServiceFactory = $languageServiceFactory;
        $this->solrService = $solrService;
        $this->setAiConnector();
    }

    abstract protected function getExtensionKey(): string;

    protected function setAiConnector(): void
    {
        $extConfAiConnector = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('w3c_aiconnector');
        $extConf = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get($this->getExtensionKey());
        $provider = $extConf['provider'] ?? $extConfAiConnector['provider'] ?? '';
        $this->aiConnector = $this->aiConnectorFactory->create($provider);
    }

    protected function searchSolr(string $keywords, SiteLanguage $siteLanguage, array $filters = []): array
    {
        return $this->solrService->search($keywords, $siteLanguage, $filters);
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

    protected function truncateSolrResultsIfNeeded(string $basePrompt, array $results): array
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
