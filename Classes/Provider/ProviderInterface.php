<?php

declare(strict_types=1);

namespace W3code\W3cAIConnector\Provider;

use Generator;

interface ProviderInterface
{
    public function process(string $prompt, array $options = [], int &$retryCount = 0, bool $stream = false): Generator|string;
    public function setConfig(): void;
}
