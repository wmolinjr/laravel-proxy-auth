<?php

namespace App\Repositories\OAuth;

use App\Entities\OAuth\ScopeEntity;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;

class ScopeRepository implements ScopeRepositoryInterface
{
    /**
     * Available scopes
     */
    protected array $scopes = [
        'openid' => 'OpenID Connect',
        'profile' => 'Access to profile information',
        'email' => 'Access to email address',
    ];

    /**
     * Get scope entity by identifier
     */
    public function getScopeEntityByIdentifier(string $identifier): ?ScopeEntityInterface
    {
        if (!array_key_exists($identifier, $this->scopes)) {
            return null;
        }

        return new ScopeEntity($identifier, $this->scopes[$identifier]);
    }

    /**
     * Finalize scopes - filter and validate scopes for client
     */
    public function finalizeScopes(
        array $scopes,
        string $grantType,
        ClientEntityInterface $clientEntity,
        ?string $userIdentifier = null
    ): array {
        // Validar se os escopos são permitidos
        $validScopes = [];
        
        foreach ($scopes as $scope) {
            if (array_key_exists($scope->getIdentifier(), $this->scopes)) {
                $validScopes[] = $scope;
            }
        }

        // Se não tem escopos válidos e é OIDC, adicionar openid como padrão
        if (empty($validScopes) && $grantType === 'authorization_code') {
            $openidScope = $this->getScopeEntityByIdentifier('openid');
            if ($openidScope) {
                $validScopes[] = $openidScope;
            }
        }

        return $validScopes;
    }

    /**
     * Get all available scopes
     */
    public function getAvailableScopes(): array
    {
        return $this->scopes;
    }
}