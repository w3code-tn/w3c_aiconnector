<?php
declare(strict_types=1);

namespace W3code\W3cAiconnector\Service;

use TYPO3\CMS\Core\Utility\GeneralUtility;

class SearchServiceFactory
{
    public function create(string $serviceName): ?object
    {
        $className = __NAMESPACE__ . '\\' . ucfirst($serviceName) . 'Service';
        if (class_exists($className)) {
            return GeneralUtility::makeInstance($className);
        } else {
            return null;
        }
    }
}
