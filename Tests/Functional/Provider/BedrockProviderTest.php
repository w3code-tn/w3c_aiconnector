<?php

declare(strict_types=1);

namespace W3code\W3cAIConnector\Tests\Functional\Provider;

use Aws\BedrockRuntime\BedrockRuntimeClient;
use Aws\Result;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use W3code\W3cAIConnector\Provider\BedrockProvider;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use GuzzleHttp\Psr7\Utils;

class BedrockProviderTest extends FunctionalTestCase
{
    protected array $testConfig = [
        'maxRetries' => 3,
        'provider' => 'bedrock',
        'bedrock' => [
            'accessKeyId' => 'test-key',
            'secretAccessKey' => 'test-secret',
            'region' => 'us-east-1',
            'modelId' => 'anthropic.claude-3-sonnet-20240229-v1:0',
            'maxTokens' => 512,
            'temperature' => 0.5,
            'chunkSize' => 10,
            'maxInputTokensAllowed' => 1000,
            'fallbackModels' => '',
        ]
    ];

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mocking ExtensionConfiguration to return our test config
        $extensionConfigurationMock = $this->getMockBuilder(ExtensionConfiguration::class)
            ->disableOriginalConstructor()
            ->getMock();
        $extensionConfigurationMock->method('get')->willReturn($this->testConfig);
        GeneralUtility::addInstance(ExtensionConfiguration::class, $extensionConfigurationMock);
    }

    #[Test]
    public function testProcessWithClaudeModel(): void
    {
        $mockBedrockClient = $this->getMockBuilder(BedrockRuntimeClient::class)
            ->disableOriginalConstructor()
            ->addMethods(['invokeModel'])
            ->getMock();

        $mockResponseBody = [
            'content' => [
                [
                    'text' => 'Hello from Bedrock Claude'
                ]
            ]
        ];

        $mockBedrockClient->expects(self::once())
            ->method('invokeModel')
            ->with(self::callback(function($args) {
                return $args['modelId'] === 'anthropic.claude-3-sonnet-20240229-v1:0' &&
                       str_contains($args['body'], 'anthropic_version');
            }))
            ->willReturn(new Result([
                'body' => Utils::streamFor(json_encode($mockResponseBody))
            ]));

        $provider = new class($mockBedrockClient) extends BedrockProvider {
            public function __construct($client) {
                parent::__construct();
                $this->client = $client;
            }
        };

        $result = $provider->process('Say hello');
        self::assertSame('Hello from Bedrock Claude', $result);
    }

    #[Test]
    public function testProcessWithLlamaModel(): void
    {
        $mockBedrockClient = $this->getMockBuilder(BedrockRuntimeClient::class)
            ->disableOriginalConstructor()
            ->addMethods(['invokeModel'])
            ->getMock();

        $mockResponseBody = [
            'generation' => 'Hello from Bedrock Llama'
        ];

        $mockBedrockClient->expects(self::once())
            ->method('invokeModel')
            ->with(self::callback(function($args) {
                return $args['modelId'] === 'meta.llama-test' &&
                       str_contains($args['body'], 'prompt');
            }))
            ->willReturn(new Result([
                'body' => Utils::streamFor(json_encode($mockResponseBody))
            ]));

        $provider = new class($mockBedrockClient) extends BedrockProvider {
            public function __construct($client) {
                parent::__construct();
                $this->client = $client;
            }
        };

        $result = $provider->process('Say hello', ['model' => 'meta.llama-test']);
        self::assertSame('Hello from Bedrock Llama', $result);
    }

    #[Test]
    public function testProcessStream(): void
    {
        $mockBedrockClient = $this->getMockBuilder(BedrockRuntimeClient::class)
            ->disableOriginalConstructor()
            ->addMethods(['invokeModelWithResponseStream'])
            ->getMock();

        $events = [
            ['chunk' => ['bytes' => json_encode(['delta' => ['text' => 'Hello ']])]],
            ['chunk' => ['bytes' => json_encode(['delta' => ['text' => 'World']])]],
        ];

        $mockBedrockClient->expects(self::once())
            ->method('invokeModelWithResponseStream')
            ->willReturn(new Result([
                'body' => $events
            ]));

        $provider = new class($mockBedrockClient) extends BedrockProvider {
            public function __construct($client) {
                parent::__construct();
                $this->client = $client;
            }
        };

        $generator = $provider->processStream('Say hello');
        $results = iterator_to_array($generator);

        self::assertSame(['Hello ', 'World'], $results);
    }

    #[Test]
    public function testSetupDecodesAbskCredentials(): void
    {
        $mockConfig = [
            'maxRetries' => 3,
            'provider' => 'bedrock',
            'bedrock' => [
                'accessKeyId' => getenv('BEDROCK_API_KEY'),
                'secretAccessKey' => getenv('BEDROCK_API_SECRET'),
                'region' => 'us-east-1',
                'modelId' => 'anthropic.claude-3-sonnet-20240229-v1:0',
                'maxTokens' => 512,
                'temperature' => 0.5,
                'chunkSize' => 10,
                'maxInputTokensAllowed' => 1000,
                'fallbackModels' => '',
            ]
        ];

        $extensionConfigurationMock = $this->getMockBuilder(ExtensionConfiguration::class)
            ->disableOriginalConstructor()
            ->getMock();
        $extensionConfigurationMock->method('get')->willReturn($mockConfig);
        GeneralUtility::addInstance(ExtensionConfiguration::class, $extensionConfigurationMock);

        $provider = new BedrockProvider();

        self::assertSame('BedrockAPIKey-tk6p-at-902664881324', $provider->config['accessKeyId']);
        self::assertSame('CWzR55z/9XmLP9cZNEX+i9TZc1OxIRaz5ObXz+QTduwUHFbqR00tsTok9vw=', $provider->config['secretAccessKey']);
    }
}
