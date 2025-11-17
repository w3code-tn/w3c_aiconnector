<?php

declare(strict_types=1);

namespace W3c\W3cAiconnector\Tests\Integration\Client;

use GuzzleHttp\Client;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use W3code\W3cAIConnector\Client\DeepLClient;

class DeepLClientIntegrationTest extends FunctionalTestCase
{
    #[Test]
    public function testGenerateResponseWithRealApiKey(): void
    {
        $apiKey = getenv('DEEPL_API_KEY');
        if (!$apiKey) {
            self::markTestSkipped('DEEPL_API_KEY environment variable not set');
        }

        $client = new DeepLClient();
        $client->setClient(new Client());

        $response = $client->generateResponse(
            'test prompt',
            [
                'apiKey' => $apiKey,
                'target_lang' => 'EN',
                'model' => 'deepl-translator',
            ]
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertNotEmpty($response->getBody()->getContents());
    }
}
