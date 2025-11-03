<?php

declare(strict_types=1);

namespace W3c\W3cAiconnector\Tests\Functional\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use W3code\W3cAIConnector\Client\GeminiClient;

class GeminiClientTest extends FunctionalTestCase
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
                'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=test-api-key',
                [
                    'json' => [
                        'contents' => [
                            [
                                'role' => 'user',
                                'parts' => [
                                    ['text' => 'test prompt'],
                                ],
                            ],
                        ],
                    ],
                    'stream' => false,
                ]
            )
            ->willReturn(new Response(200, [], 'test response'));

        $client = new GeminiClient();
        $client->setClient($mockClient);

        $response = $client->generateResponse(
            'test prompt',
            [
                'apiKey' => 'test-api-key',
                'model' => 'gemini-2.0-flash',
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
                'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:streamGenerateContent?key=test-api-key',
                [
                    'json' => [
                        'contents' => [
                            [
                                'role' => 'user',
                                'parts' => [
                                    ['text' => 'test prompt'],
                                ],
                            ],
                        ],
                    ],
                    'stream' => true,
                ]
            )
            ->willReturn(new Response(200, [], 'test response'));

        $client = new GeminiClient();
        $client->setClient($mockClient);

        $response = $client->generateResponse(
            'test prompt',
            [
                'apiKey' => 'test-api-key',
                'model' => 'gemini-2.0-flash',
            ],
            true
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('test response', $response->getBody()->getContents());
    }
}
