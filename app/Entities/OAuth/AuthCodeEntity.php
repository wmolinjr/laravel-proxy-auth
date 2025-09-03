<?php

namespace App\Entities\OAuth;

use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\Traits\AuthCodeTrait;

class AuthCodeEntity implements AuthCodeEntityInterface
{
    use AuthCodeTrait;

    protected string $identifier;
    protected \DateTimeImmutable $expiryDateTime;
    protected ?string $userIdentifier;
    protected array $scopes = [];
    protected ClientEntityInterface $client;

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier($identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getExpiryDateTime(): \DateTimeImmutable
    {
        return $this->expiryDateTime;
    }

    public function setExpiryDateTime(\DateTimeImmutable $dateTime): void
    {
        $this->expiryDateTime = $dateTime;
    }

    public function getUserIdentifier(): ?string
    {
        return $this->userIdentifier;
    }

    public function setUserIdentifier($identifier): void
    {
        $this->userIdentifier = $identifier;
    }

    public function getClient(): ClientEntityInterface
    {
        return $this->client;
    }

    public function setClient(ClientEntityInterface $client): void
    {
        $this->client = $client;
    }

    public function addScope($scope): void
    {
        $this->scopes[] = $scope;
    }

    public function getScopes(): array
    {
        return $this->scopes;
    }
}