<?php

namespace App\Repositories\OAuth;

use App\Entities\OAuth\AccessTokenEntity;
use App\Models\OAuth\OAuthAccessToken;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;

class AccessTokenRepository implements AccessTokenRepositoryInterface
{
    /**
     * Create a new access token entity
     */
    public function getNewToken(ClientEntityInterface $clientEntity, array $scopes, ?string $userIdentifier = null): AccessTokenEntityInterface
    {
        $accessToken = new AccessTokenEntity();
        $accessToken->setClient($clientEntity);
        
        foreach ($scopes as $scope) {
            $accessToken->addScope($scope);
        }
        
        if ($userIdentifier) {
            $accessToken->setUserIdentifier($userIdentifier);
        }

        return $accessToken;
    }

    /**
     * Persist access token to database
     */
    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity): void
    {
        $scopes = array_map(function (ScopeEntityInterface $scope) {
            return $scope->getIdentifier();
        }, $accessTokenEntity->getScopes());

        OAuthAccessToken::create([
            'id' => $accessTokenEntity->getIdentifier(),
            'user_id' => $accessTokenEntity->getUserIdentifier(),
            'client_id' => $accessTokenEntity->getClient()->getIdentifier(),
            'scopes' => implode(' ', $scopes),
            'revoked' => false,
            'expires_at' => $accessTokenEntity->getExpiryDateTime(),
        ]);
    }

    /**
     * Revoke an access token
     */
    public function revokeAccessToken(string $tokenId): void
    {
        $token = OAuthAccessToken::find($tokenId);
        if ($token) {
            $token->revoked = true;
            $token->save();
        }
    }

    /**
     * Check if access token is revoked
     */
    public function isAccessTokenRevoked(string $tokenId): bool
    {
        $token = OAuthAccessToken::find($tokenId);
        
        if (!$token) {
            return true;
        }

        return $token->revoked || $token->isExpired();
    }
}