<?php

declare(strict_types=1);

namespace W3c\W3cAiconnector\Tests\Integration\Client;

use GuzzleHttp\Client;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use W3code\W3cAIConnector\Client\ClaudeClient;

class ClaudeClientIntegrationTest extends FunctionalTestCase
{
    #[Test]
    public function testGenerateResponseWithRealApiKey(): void
    {
        $apiKey = getenv('CLAUDE_API_KEY');
        if (!$apiKey) {
            self::markTestSkipped('CLAUDE_API_KEY environment variable not set');
        }

        $client = new ClaudeClient();
        $client->setClient(new Client());

        $response = $client->generateResponse(
            'test prompt',
            [
                'apiKey' => $apiKey,
                'apiVersion' => '2023-06-01',
                'model' => 'claude-opus-4-1-20250805',
                'maxTokens' => 1024,
            ]
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertNotEmpty($response->getBody()->getContents());
    }
}
