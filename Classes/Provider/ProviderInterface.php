<?php

declare(strict_types=1);

namespace W3code\W3cAIConnector\Provider;

use Generator;

interface ProviderInterface
{
    /**
     * setup the AI provider
     * useful for setting configuration parameters or any other setu
     */
    public function setup(): void;

    /**
     * return the current configuration of the provider
     *
     * @return array
     */
    public function getConfig(): array;

    /**
     * process the response from the AI provider
     *
     * @param string $prompt
     * @param array $options
     * @param int $retryCount
     * @param bool $stream
     *
     * @return string|Generator
     */
    public function process(string $prompt, array $options = [], int &$retryCount = 0, bool $stream = false): string|Generator;
}
