<?php
declare(strict_types=1);

namespace W3c\W3cAiconnector\Tests\Functional\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use W3code\W3cAIConnector\Client\CohereClient;

class CohereClientTest extends FunctionalTestCase
{

    #[Test]
    public function testGenerateResponse(): void
    {
        $mockClient = $this->getMockBuilder(Client::class)
            ->onlyMethods(['post'])
            ->getMock();
        $mockClient->expects($this->once())
            ->method('post')
            ->with(
                'https://api.cohere.ai/v1/chat',
                [
                    'headers' => [
                        'Authorization' => 'Bearer test-api-key',
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'model' => 'command-a-03-2025',
                        'message' => 'test prompt',
                    ],
                    'stream' => false,
                ]
            )
            ->willReturn(new Response(200, [], 'test response'));

        $client = new CohereClient();
        $client->setClient($mockClient);

        $response = $client->generateResponse(
            'test prompt',
            [
                'apiKey' => 'test-api-key',
                'model' => 'command-a-03-2025',
            ]
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('test response', $response->getBody()->getContents());
    }

    #[Test]
    public function testGenerateResponseWithStream(): void
    {
        $mockClient = $this->getMockBuilder(Client::class)
            ->onlyMethods(['post'])
            ->getMock();
        $mockClient->expects($this->once())
            ->method('post')
            ->with(
                'https://api.cohere.ai/v1/chat',
                [
                    'headers' => [
                        'Authorization' => 'Bearer test-api-key',
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'model' => 'command-a-03-2025',
                        'message' => 'test prompt',
                        'stream' => true,
                    ],
                    'stream' => true,
                ]
            )
            ->willReturn(new Response(200, [], 'test response'));

        $client = new CohereClient();
        $client->setClient($mockClient);

        $response = $client->generateResponse(
            'test prompt',
            [
                'apiKey' => 'test-api-key',
                'model' => 'command-a-03-2025',
            ],
            true
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('test response', $response->getBody()->getContents());
    }
}
