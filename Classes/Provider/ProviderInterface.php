<?php

declare(strict_types=1);

namespace W3code\W3cAIConnector\Provider;

use Generator;

interface ProviderInterface
{
    /**
     * setup the AI provider
     */
    public function setup(): void;

    /**
     * process the response from the AI provider
     *
     * @param string $prompt
     * @param array $options
     *
     * @return string
     */
    public function process(string $prompt, array $options = []): string;

    /**
     * process the streamed response from the AI provider
     *
     * @param string $prompt
     * @param array $options
     *
     * @return Generator
     */
    public function processStream(string $prompt, array $options = []): Generator;
}
