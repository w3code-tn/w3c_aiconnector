<?php

declare(strict_types=1);

namespace W3c\W3cAiconnector\Tests\Integration\Client;

use GuzzleHttp\Client;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use W3code\W3cAIConnector\Client\GeminiClient;

class GeminiClientIntegrationTest extends FunctionalTestCase
{
    #[Test]
    public function testGenerateResponseWithRealApiKey(): void
    {
        $apiKey = getenv('GEMINI_API_KEY');
        if (!$apiKey) {
            self::markTestSkipped('GEMINI_API_KEY environment variable not set');
        }

        $client = new GeminiClient();
        $client->setClient(new Client());

        $response = $client->generateResponse(
            'test prompt',
            [
                'apiKey' => $apiKey,
                'model' => 'gemini-2.0-flash',
            ]
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertNotEmpty($response->getBody()->getContents());
    }
}
