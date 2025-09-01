<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Services\OAuth\JwtService;
use Illuminate\Http\JsonResponse;

class DiscoveryController extends Controller
{
    protected JwtService $jwtService;

    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    /**
     * OpenID Connect Discovery Document
     * RFC 8414 - OAuth 2.0 Authorization Server Metadata
     * OIDC Discovery spec
     */
    public function openidConfiguration(): JsonResponse
    {
        $baseUrl = config('oauth.issuer');
        
        $configuration = [
            // Required fields
            'issuer' => $baseUrl,
            'authorization_endpoint' => $baseUrl . '/oauth/authorize',
            'token_endpoint' => $baseUrl . '/oauth/token',
            'userinfo_endpoint' => $baseUrl . '/oauth/userinfo',
            'jwks_uri' => $baseUrl . '/.well-known/jwks.json',

            // Supported response types
            'response_types_supported' => [
                'code',
                'token',
                'id_token',
                'code token',
                'code id_token',
                'token id_token',
                'code token id_token',
            ],

            // Supported response modes
            'response_modes_supported' => [
                'query',
                'fragment',
                'form_post',
            ],

            // Supported grant types
            'grant_types_supported' => [
                'authorization_code',
                'refresh_token',
            ],

            // Subject types supported
            'subject_types_supported' => [
                'public',
            ],

            // ID token signing algorithms
            'id_token_signing_alg_values_supported' => [
                'RS256',
            ],

            // Scopes supported
            'scopes_supported' => array_keys(config('oauth.scopes')),

            // Claims supported
            'claims_supported' => [
                'sub',
                'iss',
                'aud',
                'exp',
                'iat',
                'auth_time',
                'nonce',
                'name',
                'preferred_username',
                'email',
                'email_verified',
                'updated_at',
            ],

            // Token endpoint authentication methods
            'token_endpoint_auth_methods_supported' => [
                'client_secret_basic',
                'client_secret_post',
            ],

            // Additional OAuth 2.0 features
            'code_challenge_methods_supported' => [
                'plain',
                'S256',
            ],

            // OIDC specific features
            'userinfo_signing_alg_values_supported' => [
                'none', // UserInfo endpoint returns JSON, not JWT
            ],

            // Optional features
            'request_uri_parameter_supported' => false,
            'request_parameter_supported' => false,
            'require_request_uri_registration' => false,

            // Service documentation
            'service_documentation' => $baseUrl . '/docs',
            'op_policy_uri' => $baseUrl . '/policy',
            'op_tos_uri' => $baseUrl . '/terms',

            // Custom WMJ fields
            'provider_name' => 'WMJ Identity Provider',
            'provider_version' => '1.0.0',
            'supported_claims_locales' => ['pt-BR', 'en'],
        ];

        return response()->json($configuration)
            ->header('Cache-Control', 'public, max-age=3600') // Cache for 1 hour
            ->header('Content-Type', 'application/json');
    }

    /**
     * JSON Web Key Set (JWKS) for token verification
     * RFC 7517 - JSON Web Key (JWK)
     */
    public function jwks(): JsonResponse
    {
        try {
            $jwks = $this->jwtService->getJwks();

            \Log::info('JWKS requested successfully');

            return response()->json($jwks)
                ->header('Cache-Control', 'public, max-age=86400') // Cache for 24 hours
                ->header('Content-Type', 'application/json');

        } catch (\Exception $exception) {
            \Log::error('Failed to generate JWKS', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'server_error',
                'error_description' => 'Failed to generate JSON Web Key Set',
            ], 500);
        }
    }

    /**
     * OAuth 2.0 Authorization Server Metadata (RFC 8414)
     * This is similar to OIDC discovery but for pure OAuth 2.0
     */
    public function oauth2Metadata(): JsonResponse
    {
        $baseUrl = config('oauth.issuer');

        $metadata = [
            'issuer' => $baseUrl,
            'authorization_endpoint' => $baseUrl . '/oauth/authorize',
            'token_endpoint' => $baseUrl . '/oauth/token',
            'jwks_uri' => $baseUrl . '/.well-known/jwks.json',
            
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'token_endpoint_auth_methods_supported' => ['client_secret_basic', 'client_secret_post'],
            'scopes_supported' => array_keys(config('oauth.scopes')),
            'code_challenge_methods_supported' => ['plain', 'S256'],
            
            'service_documentation' => $baseUrl . '/docs/oauth2',
        ];

        return response()->json($metadata)
            ->header('Cache-Control', 'public, max-age=3600')
            ->header('Content-Type', 'application/json');
    }

    /**
     * Health check endpoint for monitoring
     */
    public function health(): JsonResponse
    {
        try {
            // Basic health checks
            $checks = [
                'database' => $this->checkDatabase(),
                'jwt_keys' => $this->checkJwtKeys(),
                'cache' => $this->checkCache(),
            ];

            $healthy = !in_array(false, $checks);
            $status = $healthy ? 'healthy' : 'unhealthy';

            return response()->json([
                'status' => $status,
                'timestamp' => now()->toISOString(),
                'version' => config('app.version', '1.0.0'),
                'checks' => $checks,
            ], $healthy ? 200 : 503);

        } catch (\Exception $exception) {
            return response()->json([
                'status' => 'error',
                'error' => $exception->getMessage(),
                'timestamp' => now()->toISOString(),
            ], 500);
        }
    }

    /**
     * Check database connectivity
     */
    protected function checkDatabase(): bool
    {
        try {
            \DB::connection()->getPdo();
            return true;
        } catch (\Exception $exception) {
            \Log::error('Database health check failed', ['error' => $exception->getMessage()]);
            return false;
        }
    }

    /**
     * Check JWT keys availability
     */
    protected function checkJwtKeys(): bool
    {
        try {
            $this->jwtService->getJwks();
            return true;
        } catch (\Exception $exception) {
            \Log::error('JWT keys health check failed', ['error' => $exception->getMessage()]);
            return false;
        }
    }

    /**
     * Check cache connectivity
     */
    protected function checkCache(): bool
    {
        try {
            \Cache::put('health_check', true, 60);
            return \Cache::get('health_check') === true;
        } catch (\Exception $exception) {
            \Log::error('Cache health check failed', ['error' => $exception->getMessage()]);
            return false;
        }
    }
}