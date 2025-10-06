<?php
declare(strict_types=1);

namespace W3code\W3cAIConnector\DependencyInjection;

use Psr\Container\ContainerInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Trait ContainerAwareTrait
 */
trait ContainerAwareTrait
{
    /**
     * @return ContainerInterface
     */
    public function getContainer(): ContainerInterface
    {
        return GeneralUtility::getContainer();
    }
}
