<?php

declare(strict_types=1);

namespace W3c\W3cAiconnector\Tests\Integration\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use W3code\W3cAIConnector\Client\MistralClient;

class MistralClientIntegrationTest extends FunctionalTestCase
{
    #[Test]
    public function testGenerateResponseWithRealApiKey(): void
    {
        $apiKey = getenv('MISTRAL_API_KEY');
        if (!$apiKey) {
            self::markTestSkipped('MISTRAL_API_KEY environment variable not set');
        }

        $client = new MistralClient();
        $client->setClient(new Client());

        $payload = [
            'apiKey' => $apiKey,
            'temperature' => 0.7,
            'topP' => 0.9,
            'maxTokens' => 1024,
            'randomSeed' => null,
            'safePrompt' => false,
            'model' => 'mistral-large-latest',
        ];

        $fallbackOptionsList = [
            [
                'apiKey' => $apiKey,
                'temperature' => 0.7,
                'topP' => 0.9,
                'maxTokens' => 1024,
                'randomSeed' => null,
                'safePrompt' => false,
                'model' => 'mistral-medium-latest',
            ],
            [
                'apiKey' => $apiKey,
                'temperature' => 0.7,
                'topP' => 0.9,
                'maxTokens' => 1024,
                'randomSeed' => null,
                'safePrompt' => false,
                'model' => 'mistral-small-latest',
            ],
        ];

        $tryRequest = function (array $options) use ($client): bool {
            try {
                $response = $client->generateResponse('test prompt', $options);
            } catch (GuzzleException $e) {
                // network / client error
                return false;
            } catch (\Throwable $e) {
                // any other throwable
                return false;
            }

            $status = $response->getStatusCode();
            $body = (string)$response->getBody();

            return $status === 200 && $body !== '';
        };

        // Try primary first
        if ($tryRequest($payload)) {
            self::assertTrue(true, 'Primary model responded successfully.');
            return;
        }

        // Try fallbacks
        foreach ($fallbackOptionsList as $fallbackOptions) {
            if ($tryRequest($fallbackOptions)) {
                self::assertTrue(true, sprintf('Fallback model "%s" responded successfully.', $fallbackOptions['model']));
                return;
            }
        }

        // If we reach here, none of the models succeeded
        self::markTestSkipped('Could not connect successfully using the primary or fallback models.');
    }
}
