<?php

declare(strict_types=1);

namespace W3code\W3cAIConnector\Client;

use Generator;
use Psr\Http\Message\ResponseInterface;

interface ClientInterface
{
    /**
     * manipulate the content
     *
     * @param string $prompt
     * @param array $options
     * @param bool $stream
     * @return mixed
     */
    public function getContent(string $prompt, array $options = [], bool $stream = false): mixed;

    /**
     * generates the response from the api endpoint
     *
     * @param string $prompt
     * @param array $options
     * @param bool $stream
     * @return ResponseInterface
     */
    public function generateResponse(string $prompt, array $options = [], bool $stream = false): ResponseInterface;
}
