<?php

declare(strict_types=1);

namespace W3c\W3cAiconnector\Tests\Integration\Client;

use GuzzleHttp\Client;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use W3code\W3cAIConnector\Client\GoogleTranslateClient;

class GoogleTranslateClientIntegrationTest extends FunctionalTestCase
{
    #[Test]
    public function testGenerateResponseWithRealApiKey(): void
    {
        $apiKey = getenv('GOOGLE_TRANSLATE_API_KEY');
        if (!$apiKey) {
            self::markTestSkipped('GOOGLE_TRANSLATE_API_KEY environment variable not set');
        }

        $client = new GoogleTranslateClient();
        $client->setClient(new Client());

        $response = $client->generateResponse(
            'test prompt',
            [
                'apiKey' => $apiKey,
                'targetLang' => 'en'
            ]
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertNotEmpty($response->getBody()->getContents());
    }
}
