<?php

namespace App\Entities\OAuth;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\Traits\ClientTrait;

class ClientEntity implements ClientEntityInterface
{
    use ClientTrait;

    protected string $name;
    protected array $redirectUris;

    public function __construct(string $identifier, string $name, array $redirectUris)
    {
        $this->setIdentifier($identifier);
        $this->name = $name;
        $this->redirectUris = $redirectUris;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getRedirectUri(): array
    {
        return $this->redirectUris;
    }
}