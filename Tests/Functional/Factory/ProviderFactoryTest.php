<?php

declare(strict_types=1);

namespace W3c\W3cAiconnector\Tests\Functional\Factory;

use Exception;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use W3code\W3cAIConnector\Factory\ProviderFactory;
use W3code\W3cAIConnector\Provider\ProviderInterface;

class ProviderFactoryTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    #[Test]
    public function testCreateProviderName(): void
    {
        $providerName = 'testProvider';
        $providerInstance = $this->createMock(ProviderInterface::class);

        $GLOBALS['TYPO3_CONF_VARS']['EXT']['w3c_aiconnector'][$providerName] = get_class($providerInstance);

        $providerFactory = new ProviderFactory();
        $result = $providerFactory->create($providerName);

        self::assertInstanceOf(ProviderInterface::class, $result);
    }

    #[Test]
    public function testGetProviderInstance(): void
    {
        $providerName = 'testProvider';
        $providerInstance = $this->createMock(ProviderInterface::class);

        $GLOBALS['TYPO3_CONF_VARS']['EXT']['w3c_aiconnector'][$providerName] = get_class($providerInstance);

        $providerFactory = new ProviderFactory();
        $result = $providerFactory->getProviderInstance($providerName);

        self::assertInstanceOf(ProviderInterface::class, $result);
    }

    #[Test]
    public function testGetProviderInstanceWithUnknownProvider(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No provider instance found for the identifier "unknownProvider"');
        $this->expectExceptionCode(1504017523);

        $GLOBALS['TYPO3_CONF_VARS']['EXT']['w3c_aiconnector']['unknownProvider'] = null;

        $providerFactory = new ProviderFactory();
        $providerFactory->getProviderInstance('unknownProvider');
    }
}
