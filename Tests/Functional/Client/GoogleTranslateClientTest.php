<?php

declare(strict_types=1);

namespace W3c\W3cAiconnector\Tests\Functional\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use W3code\W3cAIConnector\Client\GoogleTranslateClient;

class GoogleTranslateClientTest extends FunctionalTestCase
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
                'https://translation.googleapis.com/language/translate/v2?key=test-api-key',
                [
                    'json' => [
                        'q' => 'test prompt',
                        'target' => 'de',
                    ],
                ]
            )
            ->willReturn(new Response(200, [], 'test response'));

        $client = new GoogleTranslateClient();
        $client->setClient($mockClient);

        $response = $client->generateResponse(
            'test prompt',
            [
                'apiKey' => 'test-api-key',
                'targetLang' => 'de',
            ]
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('test response', $response->getBody()->getContents());
    }
}
