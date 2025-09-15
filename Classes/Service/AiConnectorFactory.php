<?php

declare(strict_types=1);

namespace W3code\W3cAiconnector\Service;

use W3code\W3cAiconnector\Interface\AiConnectorInterface;

class AiConnectorFactory
{
    private array $services;
    private ?AiConnectorInterface $customService;

    public function __construct(array $services, ?AiConnectorInterface $customService = null)
    {
        $this->services = $services;
        $this->customService = $customService;
    }

    public function create(string $provider): ?AiConnectorInterface
    {
        if (isset($this->services[$provider])) {
            return $this->services[$provider];
        }

        // Si la config est 'custom', on utilise le service injecté par le conteneur.
        if ($provider === 'custom' && $this->customService) {
            return $this->customService;
        }

        return null;
    }
}
