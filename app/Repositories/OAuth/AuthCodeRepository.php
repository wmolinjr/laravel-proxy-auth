<?php

namespace App\Repositories\OAuth;

use App\Entities\OAuth\AuthCodeEntity;
use App\Models\OAuth\OAuthAuthorizationCode;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;

class AuthCodeRepository implements AuthCodeRepositoryInterface
{
    /**
     * Create a new authorization code entity
     */
    public function getNewAuthCode(): AuthCodeEntityInterface
    {
        return new AuthCodeEntity();
    }

    /**
     * Persist authorization code to database
     */
    public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity): void
    {
        $scopes = array_map(function ($scope) {
            return $scope->getIdentifier();
        }, $authCodeEntity->getScopes());

        OAuthAuthorizationCode::create([
            'id' => $authCodeEntity->getIdentifier(),
            'user_id' => $authCodeEntity->getUserIdentifier(),
            'client_id' => $authCodeEntity->getClient()->getIdentifier(),
            'scopes' => implode(' ', $scopes),
            'revoked' => false,
            'expires_at' => $authCodeEntity->getExpiryDateTime(),
        ]);
    }

    /**
     * Revoke authorization code
     */
    public function revokeAuthCode(string $codeId): void
    {
        $code = OAuthAuthorizationCode::find($codeId);
        if ($code) {
            $code->revoke();
        }
    }

    /**
     * Check if authorization code is revoked
     */
    public function isAuthCodeRevoked(string $codeId): bool
    {
        $code = OAuthAuthorizationCode::find($codeId);
        
        if (!$code) {
            return true;
        }

        return !$code->isValid();
    }
}