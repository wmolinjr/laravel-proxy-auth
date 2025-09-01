<?php

namespace App\Services\OAuth;

use App\Repositories\OAuth\AccessTokenRepository;
use App\Repositories\OAuth\AuthCodeRepository;
use App\Repositories\OAuth\ClientRepository;
use App\Repositories\OAuth\RefreshTokenRepository;
use App\Repositories\OAuth\ScopeRepository;
use App\Repositories\OAuth\UserRepository;
use DateInterval;
use Illuminate\Support\Facades\Log;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\ResourceServer;

class OAuthServerService
{
    protected AuthorizationServer $authorizationServer;
    protected ResourceServer $resourceServer;
    protected ClientRepository $clientRepository;
    protected AccessTokenRepository $accessTokenRepository;
    protected ScopeRepository $scopeRepository;
    protected AuthCodeRepository $authCodeRepository;
    protected RefreshTokenRepository $refreshTokenRepository;
    protected UserRepository $userRepository;

    public function __construct()
    {
        $this->initializeRepositories();
        $this->initializeAuthorizationServer();
        $this->initializeResourceServer();
    }

    /**
     * Get Authorization Server instance
     */
    public function getAuthorizationServer(): AuthorizationServer
    {
        return $this->authorizationServer;
    }

    /**
     * Get Resource Server instance
     */
    public function getResourceServer(): ResourceServer
    {
        return $this->resourceServer;
    }

    /**
     * Get repository instances
     */
    public function getClientRepository(): ClientRepository
    {
        return $this->clientRepository;
    }

    public function getAccessTokenRepository(): AccessTokenRepository
    {
        return $this->accessTokenRepository;
    }

    public function getScopeRepository(): ScopeRepository
    {
        return $this->scopeRepository;
    }

    /**
     * Initialize repository instances
     */
    protected function initializeRepositories(): void
    {
        $this->clientRepository = new ClientRepository();
        $this->accessTokenRepository = new AccessTokenRepository();
        $this->scopeRepository = new ScopeRepository();
        $this->authCodeRepository = new AuthCodeRepository();
        $this->refreshTokenRepository = new RefreshTokenRepository();
        $this->userRepository = new UserRepository();
    }

    /**
     * Initialize Authorization Server
     */
    protected function initializeAuthorizationServer(): void
    {
        try {
            $this->authorizationServer = new AuthorizationServer(
                $this->clientRepository,
                $this->accessTokenRepository,
                $this->scopeRepository,
                config('oauth.private_key'),
                config('oauth.encryption_key')
            );

            $this->configureGrants();

        } catch (\Exception $e) {
            Log::error('Failed to initialize OAuth Authorization Server', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Initialize Resource Server
     */
    protected function initializeResourceServer(): void
    {
        try {
            $this->resourceServer = new ResourceServer(
                $this->accessTokenRepository,
                config('oauth.public_key')
            );
        } catch (\Exception $e) {
            Log::error('Failed to initialize OAuth Resource Server', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Configure OAuth grants
     */
    protected function configureGrants(): void
    {
        // Authorization Code Grant (principal para web applications)
        $authCodeGrant = new AuthCodeGrant(
            $this->authCodeRepository,
            $this->refreshTokenRepository,
            new DateInterval(config('oauth.authorization_code_lifetime'))
        );

        // Configurar lifetime do refresh token
        $authCodeGrant->setRefreshTokenTTL(
            new DateInterval(config('oauth.refresh_token_lifetime'))
        );

        // Enable PKCE (Proof Key for Code Exchange) para segurança adicional
        $authCodeGrant->setRequireCodeChallengeForPublicClients(false); // Opcional por enquanto

        $this->authorizationServer->enableGrantType(
            $authCodeGrant,
            new DateInterval(config('oauth.access_token_lifetime'))
        );

        // Refresh Token Grant
        $refreshGrant = new RefreshTokenGrant($this->refreshTokenRepository);
        $refreshGrant->setRefreshTokenTTL(
            new DateInterval(config('oauth.refresh_token_lifetime'))
        );

        $this->authorizationServer->enableGrantType(
            $refreshGrant,
            new DateInterval(config('oauth.access_token_lifetime'))
        );

        Log::info('OAuth grants configured successfully', [
            'access_token_lifetime' => config('oauth.access_token_lifetime'),
            'refresh_token_lifetime' => config('oauth.refresh_token_lifetime'),
            'auth_code_lifetime' => config('oauth.authorization_code_lifetime'),
        ]);
    }

    /**
     * Validate authorization request parameters
     */
    public function validateAuthorizationRequest(array $params): array
    {
        $required = ['client_id', 'redirect_uri', 'response_type'];
        $errors = [];

        foreach ($required as $param) {
            if (!isset($params[$param]) || empty($params[$param])) {
                $errors[] = "Missing required parameter: {$param}";
            }
        }

        if ($params['response_type'] ?? null !== 'code') {
            $errors[] = 'Invalid response_type. Only "code" is supported.';
        }

        return $errors;
    }

    /**
     * Parse scopes string into array
     */
    public function parseScopes(?string $scopesString): array
    {
        if (!$scopesString) {
            return ['openid']; // Default scope for OIDC
        }

        return array_filter(explode(' ', $scopesString));
    }

    /**
     * Check if request is for OIDC (contains openid scope)
     */
    public function isOidcRequest(array $scopes): bool
    {
        return in_array('openid', $scopes);
    }
}