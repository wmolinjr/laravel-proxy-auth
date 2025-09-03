<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class OpenIdConnectController extends Controller
{
    /**
     * OpenID Connect Discovery Document
     * 
     * This endpoint provides metadata about the OpenID Provider's configuration.
     * Required by OAuth clients to discover authorization endpoints, supported scopes, etc.
     */
    public function discovery(): JsonResponse
    {
        $baseUrl = config('app.url');
        
        return response()->json([
            'issuer' => $baseUrl,
            'authorization_endpoint' => $baseUrl . '/oauth/authorize',
            'token_endpoint' => $baseUrl . '/oauth/token',
            'userinfo_endpoint' => $baseUrl . '/oauth/userinfo',
            'jwks_uri' => $baseUrl . '/oauth/jwks',
            'end_session_endpoint' => $baseUrl . '/logout',
            'registration_endpoint' => $baseUrl . '/oauth/clients',
            
            // Supported scopes
            'scopes_supported' => [
                'openid',
                'profile',
                'email',
                'read',
                'write'
            ],
            
            // Supported response types
            'response_types_supported' => [
                'code',
                'token',
                'id_token',
                'code token',
                'code id_token',
                'token id_token',
                'code token id_token'
            ],
            
            // Supported response modes
            'response_modes_supported' => [
                'query',
                'fragment',
                'form_post'
            ],
            
            // Supported grant types
            'grant_types_supported' => [
                'authorization_code',
                'implicit',
                'refresh_token',
                'client_credentials'
            ],
            
            // Subject types
            'subject_types_supported' => ['public'],
            
            // Signing algorithms
            'id_token_signing_alg_values_supported' => ['RS256'],
            
            // Token endpoint auth methods
            'token_endpoint_auth_methods_supported' => [
                'client_secret_basic',
                'client_secret_post',
                'none'
            ],
            
            // Supported claims
            'claims_supported' => [
                'aud',
                'email',
                'email_verified',
                'exp',
                'family_name',
                'given_name',
                'iat',
                'iss',
                'name',
                'picture',
                'sub',
                'preferred_username'
            ],
            
            // PKCE support
            'code_challenge_methods_supported' => ['plain', 'S256'],
            
            // Service documentation
            'service_documentation' => $baseUrl . '/docs/oauth',
            'op_policy_uri' => $baseUrl . '/privacy-policy',
            'op_tos_uri' => $baseUrl . '/terms-of-service'
        ], JSON_PRETTY_PRINT);
    }

    /**
     * JWKS (JSON Web Key Set) endpoint
     * 
     * Provides public keys used to verify JWT tokens
     */
    public function jwks(): JsonResponse
    {
        // Para uma implementação completa, você precisaria das chaves públicas do Laravel Passport
        // Por enquanto, retornamos uma estrutura vazia válida
        return response()->json([
            'keys' => []
        ], JSON_PRETTY_PRINT);
    }

    /**
     * UserInfo endpoint
     * 
     * Returns claims about the authenticated user
     */
    public function userinfo(): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json([
                'error' => 'invalid_token',
                'error_description' => 'The access token provided is expired, revoked, malformed, or invalid'
            ], 401);
        }

        return response()->json([
            'sub' => (string) $user->id,
            'name' => $user->name,
            'given_name' => $this->getFirstName($user->name),
            'family_name' => $this->getLastName($user->name),
            'email' => $user->email,
            'email_verified' => !is_null($user->email_verified_at),
            'preferred_username' => $user->email,
            'picture' => $user->avatar_url ?? null,
            'updated_at' => $user->updated_at?->timestamp
        ], JSON_PRETTY_PRINT);
    }

    /**
     * Extract first name from full name
     */
    private function getFirstName(string $fullName): string
    {
        $parts = explode(' ', trim($fullName));
        return $parts[0] ?? '';
    }

    /**
     * Extract last name from full name
     */
    private function getLastName(string $fullName): string
    {
        $parts = explode(' ', trim($fullName));
        if (count($parts) > 1) {
            array_shift($parts); // Remove first name
            return implode(' ', $parts);
        }
        return '';
    }
}