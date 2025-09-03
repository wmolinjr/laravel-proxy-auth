<?php

namespace App\Repositories\OAuth;

use App\Entities\OAuth\RefreshTokenEntity;
use App\Models\OAuth\OAuthRefreshToken;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;

class RefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    /**
     * Create a new refresh token entity
     */
    public function getNewRefreshToken(): RefreshTokenEntityInterface
    {
        return new RefreshTokenEntity();
    }

    /**
     * Persist refresh token to database
     */
    public function persistNewRefreshToken(RefreshTokenEntityInterface $refreshTokenEntity): void
    {
        $identifier = $refreshTokenEntity->getIdentifier();
        \Log::error('RefreshToken FULL DEBUG', [
            'identifier' => $identifier,
            'identifier_type' => gettype($identifier),
            'identifier_length' => $identifier ? strlen($identifier) : 0,
            'has_identifier' => !empty($identifier),
            'access_token_id' => $refreshTokenEntity->getAccessToken()->getIdentifier(),
            'entity_class' => get_class($refreshTokenEntity),
            'methods_available' => get_class_methods($refreshTokenEntity),
        ]);
        
        // If identifier is null or empty, this will cause the database constraint error
        if (!$identifier) {
            \Log::error('RefreshToken identifier is null/empty - this will cause database error!');
            throw new \RuntimeException('RefreshToken identifier cannot be null or empty');
        }
        
        OAuthRefreshToken::create([
            'id' => $identifier,
            'identifier' => $identifier,
            'access_token_id' => $refreshTokenEntity->getAccessToken()->getIdentifier(),
            'revoked' => false,
            'expires_at' => $refreshTokenEntity->getExpiryDateTime(),
        ]);
    }

    /**
     * Revoke refresh token
     */
    public function revokeRefreshToken(string $tokenId): void
    {
        $token = OAuthRefreshToken::find($tokenId);
        if ($token) {
            $token->revoke();
        }
    }

    /**
     * Check if refresh token is revoked
     */
    public function isRefreshTokenRevoked(string $tokenId): bool
    {
        $token = OAuthRefreshToken::find($tokenId);
        
        if (!$token) {
            return true;
        }

        return !$token->isValid();
    }
}