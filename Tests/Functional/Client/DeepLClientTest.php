<?php

declare(strict_types=1);

namespace W3c\W3cAiconnector\Tests\Functional\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use W3code\W3cAIConnector\Client\DeepLClient;

class DeepLClientTest extends FunctionalTestCase
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
                'https://api-free.deepl.com/v2/translate',
                [
                    'form_params' => [
                        'auth_key' => 'test-api-key',
                        'text' => 'test prompt',
                        'target_lang' => 'DE',
                    ],
                ]
            )
            ->willReturn(new Response(200, [], 'test response'));

        $client = new DeepLClient();
        $client->setClient($mockClient);

        $response = $client->generateResponse(
            'test prompt',
            [
                'apiKey' => 'test-api-key',
                'target_lang' => 'DE',
            ]
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('test response', $response->getBody()->getContents());
    }

    #[Test]
    public function testGenerateResponseWithFreeApi(): void
    {
        $mockClient = $this->getMockBuilder(Client::class)
            ->onlyMethods(['post'])
            ->getMock();
        $mockClient->expects(self::once())
            ->method('post')
            ->with(
                'https://api-free.deepl.com/v2/translate',
                [
                    'form_params' => [
                        'auth_key' => 'test-api-key',
                        'text' => 'test prompt',
                        'target_lang' => 'DE',
                    ],
                ]
            )
            ->willReturn(new Response(200, [], 'test response'));

        $client = new DeepLClient();
        $client->setClient($mockClient);

        $response = $client->generateResponse(
            'test prompt',
            [
                'apiKey' => 'test-api-key',
                'target_lang' => 'DE',
                'apiVersion' => 'free',
            ]
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('test response', $response->getBody()->getContents());
    }
}
