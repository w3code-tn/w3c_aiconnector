<?php
declare(strict_types=1);

namespace W3c\W3cAiconnector\Tests\Integration\Client;

use GuzzleHttp\Client;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use W3code\W3cAIConnector\Client\MistralClient;

class MistralClientIntegrationTest extends FunctionalTestCase
{
    #[Test]
    public function testGenerateResponseWithRealApiKey(): void
    {
        $apiKey = getenv('MISTRAL_API_KEY');
        if (!$apiKey) {
            $this->markTestSkipped('MISTRAL_API_KEY environment variable not set');
        }

        $client = new MistralClient();
        $client->setClient(new Client());

        $response = $client->generateResponse(
            'test prompt',
            [
                'apiKey' => $apiKey,
                'temperature' => 0.7,
                'topP' => 0.9,
                'maxTokens' => 1024,
                'randomSeed' => null,
                'safePrompt' => false,
                'model' => 'mistral-large-latest',
            ]
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody()->getContents());
    }
}
