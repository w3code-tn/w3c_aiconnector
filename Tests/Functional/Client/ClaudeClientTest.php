<?php

declare(strict_types=1);

namespace W3c\W3cAiconnector\Tests\Functional\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use W3code\W3cAIConnector\Client\ClaudeClient;

class ClaudeClientTest extends FunctionalTestCase
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
                'https://api.anthropic.com/v1/messages',
                [
                    'headers' => [
                        'x-api-key' => 'test-api-key',
                        'anthropic-version' => '2023-06-01',
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'model' => 'claude-opus-4-1-20250805',
                        'max_tokens' => 1024,
                        'messages' => [
                            [
                                'role' => 'user',
                                'content' => 'test prompt',
                            ],
                        ],
                    ],
                    'stream' => false,
                ]
            )
            ->willReturn(new Response(200, [], 'test response'));

        $client = new ClaudeClient();
        $client->setClient($mockClient);

        $response = $client->generateResponse(
            'test prompt',
            [
                'apiKey' => 'test-api-key',
                'apiVersion' => '2023-06-01',
                'model' => 'claude-opus-4-1-20250805',
                'maxTokens' => 1024,
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
                'https://api.anthropic.com/v1/messages',
                [
                    'headers' => [
                        'x-api-key' => 'test-api-key',
                        'anthropic-version' => '2023-06-01',
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'model' => 'claude-opus-4-1-20250805',
                        'max_tokens' => 1024,
                        'messages' => [
                            [
                                'role' => 'user',
                                'content' => 'test prompt',
                            ],
                        ],
                        'stream' => true,
                    ],
                    'stream' => true,
                ]
            )
            ->willReturn(new Response(200, [], 'test response'));

        $client = new ClaudeClient();
        $client->setClient($mockClient);

        $response = $client->generateResponse(
            'test prompt',
            [
                'apiKey' => 'test-api-key',
                'apiVersion' => '2023-06-01',
                'model' => 'claude-opus-4-1-20250805',
                'maxTokens' => 1024,
            ],
            true
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('test response', $response->getBody()->getContents());
    }
}
