<?php
declare(strict_types=1);

namespace W3c\W3cAiconnector\Tests\Integration\Client;

use GuzzleHttp\Client;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use W3code\W3cAIConnector\Client\OpenAIClient;

class OpenAIClientIntegrationTest extends FunctionalTestCase
{
    #[Test]
    public function testGenerateResponseWithRealApiKey(): void
    {
        $apiKey = getenv('OPENAI_API_KEY');
        if (!$apiKey) {
            $this->markTestSkipped('OPENAI_API_KEY environment variable not set');
        }

        $client = new OpenAIClient();
        $client->setClient(new Client());

        $response = $client->generateResponse(
            'test prompt',
            [
                'apiKey' => $apiKey,
                'temperature' => 0.7,
                'topP' => 0.9,
                'max_output_tokens' => 1024,
                'model' => 'gpt-4o',
            ]
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody()->getContents());
    }
}
