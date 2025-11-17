<?php

declare(strict_types=1);

namespace W3c\W3cAiconnector\Tests\Functional\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use W3code\W3cAIConnector\Client\OpenAIClient;

class OpenAIClientTest extends FunctionalTestCase
{
    #[Test]
    public function testGenerateResponse(): void
    {
        $mockClient = $this->getMockBuilder(Client::class)
            ->onlyMethods(['post'])
            ->getMock();
        $mockClient->expects(self::once())
            ->method('post')
            ->with(
                'https://api.openai.com/v1/responses',
                [
                    'headers' => [
                        'Authorization' => 'Bearer test-api-key',
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'model' => 'gpt-4',
                        'input' => 'test prompt',
                        'temperature' => 0.7,
                        'top_p' => 1,
                        'max_output_tokens' => 1024,
                    ],
                    'stream' => false,
                ]
            )
            ->willReturn(new Response(200, [], 'test response'));

        $client = new OpenAIClient();
        $client->setClient($mockClient);

        $response = $client->generateResponse(
            'test prompt',
            [
                'apiKey' => 'test-api-key',
                'model' => 'gpt-4',
                'temperature' => 0.7,
                'topP' => 1,
                'max_output_tokens' => 1024,
            ]
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('test response', $response->getBody()->getContents());
    }

    #[Test]
    public function testGenerateResponseWithStream(): void
    {
        $mockClient = $this->getMockBuilder(Client::class)
            ->onlyMethods(['post'])
            ->getMock();
        $mockClient->expects(self::once())
            ->method('post')
            ->with(
                'https://api.openai.com/v1/responses',
                [
                    'headers' => [
                        'Authorization' => 'Bearer test-api-key',
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'model' => 'gpt-4',
                        'input' => 'test prompt',
                        'temperature' => 0.7,
                        'top_p' => 1,
                        'max_output_tokens' => 1024,
                        'stream' => true,
                    ],
                    'stream' => true,
                ]
            )
            ->willReturn(new Response(200, [], 'test response'));

        $client = new OpenAIClient();
        $client->setClient($mockClient);

        $response = $client->generateResponse(
            'test prompt',
            [
                'apiKey' => 'test-api-key',
                'model' => 'gpt-4',
                'temperature' => 0.7,
                'topP' => 1,
                'max_output_tokens' => 1024,
            ],
            true
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('test response', $response->getBody()->getContents());
    }
}
