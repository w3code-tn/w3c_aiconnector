<?php
declare(strict_types=1);

namespace W3c\W3cAiconnector\Tests\Functional\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use W3code\W3cAIConnector\Client\OllamaClient;

class OllamaClientTest extends FunctionalTestCase
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
                'http://localhost:11434/api/generate',
                [
                    'json' => [
                        'model' => 'llama3',
                        'prompt' => 'test prompt',
                        'stream' => false,
                        'options' => [
                            'temperature' => 0.8,
                            'top_p' => 0.9,
                            'num_predict' => 1024,
                        ],
                    ],
                    'timeout' => 300,
                ]
            )
            ->willReturn(new Response(200, [], 'test response'));

        $client = new OllamaClient();
        $client->setClient($mockClient);

        $response = $client->generateResponse(
            'test prompt',
            [
                'endPoint' => 'http://localhost:11434',
                'model' => 'llama3',
                'temperature' => 0.8,
                'topP' => 0.9,
                'numPredict' => 1024,
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
                'http://localhost:11434/api/generate',
                [
                    'json' => [
                        'model' => 'llama3',
                        'prompt' => 'test prompt',
                        'stream' => true,
                        'options' => [
                            'temperature' => 0.8,
                            'top_p' => 0.9,
                            'num_predict' => 1024,
                        ],
                    ],
                    'timeout' => 300,
                ]
            )
            ->willReturn(new Response(200, [], 'test response'));

        $client = new OllamaClient();
        $client->setClient($mockClient);

        $response = $client->generateResponse(
            'test prompt',
            [
                'endPoint' => 'http://localhost:11434',
                'model' => 'llama3',
                'temperature' => 0.8,
                'topP' => 0.9,
                'numPredict' => 1024,
            ],
            true
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('test response', $response->getBody()->getContents());
    }
}
