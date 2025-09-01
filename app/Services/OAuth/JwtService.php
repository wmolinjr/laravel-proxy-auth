<?php

namespace App\Services\OAuth;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Log;

class JwtService
{
    protected string $privateKey;
    protected string $publicKey;
    protected string $issuer;
    protected array $supportedClaims;

    public function __construct()
    {
        $this->privateKey = $this->loadPrivateKey();
        $this->publicKey = $this->loadPublicKey();
        $this->issuer = config('oauth.issuer');
        $this->supportedClaims = config('oauth.claims');
    }

    /**
     * Create ID Token for OIDC
     */
    public function createIdToken(User $user, string $clientId, array $scopes = [], ?string $nonce = null): string
    {
        $now = time();
        
        // Claims obrigatórios do OIDC
        $payload = [
            'iss' => $this->issuer,                    // Issuer
            'sub' => (string) $user->id,               // Subject (user ID)
            'aud' => $clientId,                        // Audience (client ID)
            'exp' => $now + 3600,                      // Expiration (1 hora)
            'iat' => $now,                             // Issued at
            'auth_time' => $now,                       // Authentication time
            'jti' => uniqid('jwt_', true),             // JWT ID
        ];

        // Adicionar nonce se fornecido (para prevenir replay attacks)
        if ($nonce) {
            $payload['nonce'] = $nonce;
        }

        // Adicionar claims baseados nos scopes solicitados
        if (in_array('profile', $scopes)) {
            $payload = array_merge($payload, $this->getProfileClaims($user));
        }

        if (in_array('email', $scopes)) {
            $payload = array_merge($payload, $this->getEmailClaims($user));
        }

        try {
            return JWT::encode($payload, $this->privateKey, 'RS256');
        } catch (\Exception $e) {
            Log::error('Failed to create ID token', [
                'user_id' => $user->id,
                'client_id' => $clientId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Verify and decode JWT token
     */
    public function verifyToken(string $token): array
    {
        try {
            return (array) JWT::decode($token, new Key($this->publicKey, 'RS256'));
        } catch (\Exception $e) {
            Log::warning('Failed to verify JWT token', [
                'error' => $e->getMessage(),
                'token_preview' => substr($token, 0, 50) . '...'
            ]);
            throw $e;
        }
    }

    /**
     * Get JWKS (JSON Web Key Set) for public key verification
     */
    public function getJwks(): array
    {
        try {
            $publicKeyResource = openssl_pkey_get_public($this->publicKey);
            $publicKeyDetails = openssl_pkey_get_details($publicKeyResource);
            
            if (!isset($publicKeyDetails['rsa'])) {
                throw new \RuntimeException('Invalid RSA public key');
            }
            
            return [
                'keys' => [
                    [
                        'kty' => 'RSA',                    // Key Type
                        'use' => 'sig',                    // Key Use (signature)
                        'alg' => 'RS256',                  // Algorithm
                        'kid' => 'wmj-oauth-key-1',       // Key ID
                        'n' => $this->base64UrlEncode($publicKeyDetails['rsa']['n']),  // Modulus
                        'e' => $this->base64UrlEncode($publicKeyDetails['rsa']['e']),  // Exponent
                    ]
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Failed to generate JWKS', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get profile claims for user
     */
    protected function getProfileClaims(User $user): array
    {
        return [
            'name' => $user->name,
            'preferred_username' => $user->name,
            'updated_at' => $user->updated_at->timestamp,
        ];
    }

    /**
     * Get email claims for user
     */
    protected function getEmailClaims(User $user): array
    {
        return [
            'email' => $user->email,
            'email_verified' => !is_null($user->email_verified_at),
        ];
    }

    /**
     * Load private key from file
     */
    protected function loadPrivateKey(): string
    {
        $keyPath = config('oauth.private_key');
        
        if (!file_exists($keyPath)) {
            throw new \RuntimeException("OAuth private key not found at: {$keyPath}");
        }

        $key = file_get_contents($keyPath);
        
        if (!$key) {
            throw new \RuntimeException("Failed to read OAuth private key from: {$keyPath}");
        }

        return $key;
    }

    /**
     * Load public key from file
     */
    protected function loadPublicKey(): string
    {
        $keyPath = config('oauth.public_key');
        
        if (!file_exists($keyPath)) {
            throw new \RuntimeException("OAuth public key not found at: {$keyPath}");
        }

        $key = file_get_contents($keyPath);
        
        if (!$key) {
            throw new \RuntimeException("Failed to read OAuth public key from: {$keyPath}");
        }

        return $key;
    }

    /**
     * Base64 URL encode (for JWKS)
     */
    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}