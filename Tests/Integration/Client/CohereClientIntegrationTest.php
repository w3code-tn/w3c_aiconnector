<?php

declare(strict_types=1);

namespace W3c\W3cAiconnector\Tests\Integration\Client;

use GuzzleHttp\Client;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use W3code\W3cAIConnector\Client\CohereClient;

class CohereClientIntegrationTest extends FunctionalTestCase
{
    #[Test]
    public function testGenerateResponseWithRealApiKey(): void
    {
        $apiKey = getenv('COHERE_API_KEY');
        if (!$apiKey) {
            self::markTestSkipped('COHERE_API_KEY environment variable not set');
        }

        $client = new CohereClient();
        $client->setClient(new Client());

        $response = $client->generateResponse(
            'test prompt',
            [
                'apiKey' => $apiKey,
                'model' => 'command-a-03-2025',
            ]
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertNotEmpty($response->getBody()->getContents());
    }
}
