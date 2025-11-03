<?php

declare(strict_types=1);

namespace W3code\W3cAIConnector\Service;

use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

interface ServiceInterface
{
    /**
     * process the response from the AI provider
     *
     * @param string $prompt
     * @param array $options
     *
     * @return string
     */
    public function search(string $keywords, SiteLanguage $siteLanguage, array $filters = []): array;
}
