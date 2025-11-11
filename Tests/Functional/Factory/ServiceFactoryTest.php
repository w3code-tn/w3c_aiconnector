<?php

declare(strict_types=1);

namespace W3c\W3cAiconnector\Tests\Functional\Factory;

use Exception;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use W3code\W3cAIConnector\Factory\ServiceFactory;
use W3code\W3cAIConnector\Service\ServiceInterface;

class ServiceFactoryTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    #[Test]
    public function testGetServiceInstance(): void
    {
        $serviceName = 'testService';
        $serviceInstance = $this->createMock(ServiceInterface::class);

        $GLOBALS['TYPO3_CONF_VARS']['EXT']['w3c_aiconnector'][$serviceName] = get_class($serviceInstance);

        $serviceFactory = new ServiceFactory();
        $result = $serviceFactory->getServiceInstance($serviceName);

        self::assertInstanceOf(ServiceInterface::class, $result);
    }

    #[Test]
    public function testGetServiceInstanceWithUnknownService(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No service instance found for the identifier "unknownService"');
        $this->expectExceptionCode(1504017523);

        $GLOBALS['TYPO3_CONF_VARS']['EXT']['w3c_aiconnector']['unknownService'] = null;

        $serviceFactory = new ServiceFactory();
        $serviceFactory->getServiceInstance('unknownService');
    }
}
