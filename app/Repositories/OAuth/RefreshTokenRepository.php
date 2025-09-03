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
        
        // WORKAROUND: If identifier is null, generate one
        if (!$identifier) {
            $identifier = bin2hex(random_bytes(32));
            $refreshTokenEntity->setIdentifier($identifier);
            \Log::warning('Generated new identifier for RefreshToken', [
                'generated_identifier' => $identifier
            ]);
        }
        
        \Log::info('RefreshToken persist debug', [
            'identifier' => $identifier,
            'access_token_id' => $refreshTokenEntity->getAccessToken()->getIdentifier(),
            'expires_at' => $refreshTokenEntity->getExpiryDateTime()->format('Y-m-d H:i:s'),
        ]);
        
        OAuthRefreshToken::create([
            'id' => $identifier,
            'identifier' => $identifier,
            'access_token_id' => $refreshTokenEntity->getAccessToken()->getIdentifier(),
            'revoked' => false,
            'expires_at' => $refreshTokenEntity->getExpiryDateTime(),
        ]);
        
        \Log::info('RefreshToken saved successfully', ['identifier' => $identifier]);
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