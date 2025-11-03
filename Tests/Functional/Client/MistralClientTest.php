<?php

declare(strict_types=1);

namespace W3c\W3cAiconnector\Tests\Functional\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use W3code\W3cAIConnector\Client\MistralClient;

class MistralClientTest extends FunctionalTestCase
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
                'https://api.mistral.ai/v1/chat/completions',
                [
                    'headers' => [
                        'Authorization' => 'Bearer test-api-key',
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'model' => 'mistral-large-latest',
                        'messages' => [
                            [
                                'role' => 'user',
                                'content' => 'test prompt',
                            ],
                        ],
                        'temperature' => 0.7,
                        'top_p' => 1,
                        'max_tokens' => 1024,
                        'random_seed' => null,
                        'stream' => false,
                        'safe_prompt' => false,
                    ],
                    false => false,
                ]
            )
            ->willReturn(new Response(200, [], 'test response'));

        $client = new MistralClient();
        $client->setClient($mockClient);

        $response = $client->generateResponse(
            'test prompt',
            [
                'apiKey' => 'test-api-key',
                'model' => 'mistral-large-latest',
                'temperature' => 0.7,
                'topP' => 1,
                'maxTokens' => 1024,
                'randomSeed' => null,
                'safePrompt' => false,
            ]
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('test response', $response->getBody()->getContents());
    }

    public function testGenerateResponseWithStream(): void
    {
        $mockClient = $this->getMockBuilder(Client::class)
            ->onlyMethods(['post'])
            ->getMock();
        $mockClient->expects(self::once())
            ->method('post')
            ->with(
                'https://api.mistral.ai/v1/chat/completions',
                [
                    'headers' => [
                        'Authorization' => 'Bearer test-api-key',
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'model' => 'mistral-large-latest',
                        'messages' => [
                            [
                                'role' => 'user',
                                'content' => 'test prompt',
                            ],
                        ],
                        'temperature' => 0.7,
                        'top_p' => 1,
                        'max_tokens' => 1024,
                        'random_seed' => null,
                        'stream' => true,
                        'safe_prompt' => false,
                    ],
                    true => true,
                ]
            )
            ->willReturn(new Response(200, [], 'test response'));

        $client = new MistralClient();
        $client->setClient($mockClient);

        $response = $client->generateResponse(
            'test prompt',
            [
                'apiKey' => 'test-api-key',
                'model' => 'mistral-large-latest',
                'temperature' => 0.7,
                'topP' => 1,
                'maxTokens' => 1024,
                'randomSeed' => null,
                'safePrompt' => false,
            ],
            true
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('test response', $response->getBody()->getContents());
    }
}
