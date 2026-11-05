<?php

declare(strict_types=1);

namespace W3code\W3cAIConnector\Tests\Integration\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use W3code\W3cAIConnector\Client\GoogleTranslateClient;

class GoogleTranslateClientIntegrationTest extends FunctionalTestCase
{
    #[Test]
    public function testGenerateResponseWithRealApiKey(): void
    {
        $apiKey = getenv('GOOGLE_TRANSLATE_API_KEY');
        if (!$apiKey) {
            self::markTestSkipped('GOOGLE_TRANSLATE_API_KEY environment variable not set');
        }

        $client = new GoogleTranslateClient();
        $client->setClient(new Client());

        try {
            $response = $client->generateResponse(
                'test prompt',
                [
                    'apiKey' => $apiKey,
                    'targetLang' => 'en',
                ]
            );
        } catch (\Exception $e) {
            $previous = $e->getPrevious();
            if ($previous instanceof ClientException) {
                $body = (string)$previous->getResponse()->getBody();
                if (str_contains($body, 'API key expired') || str_contains($body, 'API key not valid')) {
                    self::markTestSkipped('GOOGLE_TRANSLATE_API_KEY is expired or invalid');
                }
            }
            throw $e;
        }

        self::assertSame(200, $response->getStatusCode());
        self::assertNotEmpty($response->getBody()->getContents());
    }
}
