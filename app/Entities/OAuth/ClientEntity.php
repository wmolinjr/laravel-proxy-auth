<?php

namespace App\Entities\OAuth;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\Traits\ClientTrait;

class ClientEntity implements ClientEntityInterface
{
    use ClientTrait;

    protected string $identifier;
    protected string $secret;
    protected array $redirectUris;

    public function __construct(string $identifier, string $name, array $redirectUris, string $secret = '')
    {
        $this->identifier = $identifier;
        $this->name = $name;
        $this->redirectUris = $redirectUris;
        $this->secret = $secret;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getSecret(): string
    {
        return $this->secret;
    }

    public function getRedirectUri(): array
    {
        return $this->redirectUris;
    }

    public function isConfidential(): bool
    {
        return !empty($this->secret);
    }
}