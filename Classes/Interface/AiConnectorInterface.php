<?php

declare(strict_types=1);

namespace W3code\W3cAiconnector\Interface;

interface AiConnectorInterface
{
    public function process(string $prompt, array $options = []): ?string;

    public function streamProcess(string $prompt, array $options = []): \Generator;
}