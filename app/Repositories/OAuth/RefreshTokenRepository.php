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
        
        // Always ensure we have a valid identifier
        if (empty($identifier)) {
            $identifier = bin2hex(random_bytes(40));
            $refreshTokenEntity->setIdentifier($identifier);
            \Log::info('Generated identifier for RefreshToken', [
                'generated_identifier' => $identifier,
                'reason' => 'identifier_was_empty'
            ]);
        }
        
        \Log::info('RefreshToken persist debug', [
            'identifier' => $identifier,
            'identifier_length' => strlen($identifier),
            'access_token_id' => $refreshTokenEntity->getAccessToken()->getIdentifier(),
            'expires_at' => $refreshTokenEntity->getExpiryDateTime()->format('Y-m-d H:i:s'),
        ]);
        
        try {
            OAuthRefreshToken::create([
                'id' => $identifier,
                'identifier' => $identifier,
                'access_token_id' => $refreshTokenEntity->getAccessToken()->getIdentifier(),
                'revoked' => false,
                'expires_at' => $refreshTokenEntity->getExpiryDateTime(),
            ]);
            
            \Log::info('RefreshToken saved successfully', ['identifier' => $identifier]);
        } catch (\Exception $e) {
            \Log::error('Failed to save RefreshToken', [
                'identifier' => $identifier,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
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