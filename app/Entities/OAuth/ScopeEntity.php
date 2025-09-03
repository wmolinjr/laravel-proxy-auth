<?php

namespace App\Entities\OAuth;

use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Entities\Traits\ScopeTrait;

class ScopeEntity implements ScopeEntityInterface
{
    use ScopeTrait;

    protected string $identifier;
    protected string $description;

    public function __construct(string $identifier, string $description = '')
    {
        $this->identifier = $identifier;
        $this->description = $description;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }
}