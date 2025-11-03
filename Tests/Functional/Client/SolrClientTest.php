<?php

declare(strict_types=1);

namespace W3c\W3cAiconnector\Tests\Functional\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use W3code\W3cAIConnector\Client\SolrClient;

class SolrClientTest extends FunctionalTestCase
{
    #[Test]
    public function testGenerateResponse(): void
    {
        $mockClient = $this->getMockBuilder(Client::class)
            ->onlyMethods(['get'])
            ->getMock();
        $mockClient->expects(self::once())
            ->method('get')
            ->with('http://solr:8983/solr/core/select?q=test%20keywords&fq=filter1&fq=filter2')
            ->willReturn(new Response(200, [], 'test response'));

        $client = new SolrClient();
        $client->setClient($mockClient);

        $response = $client->generateResponse(
            'core',
            [],
            'test keywords',
            ['filter1', 'filter2']
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('test response', $response->getBody()->getContents());
    }
}
