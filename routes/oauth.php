<?php

use App\Http\Controllers\OAuth\AuthorizationController;
use App\Http\Controllers\OAuth\DiscoveryController;
use App\Http\Controllers\OAuth\TokenController;
use App\Http\Controllers\OAuth\UserInfoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| OAuth 2.0 / OpenID Connect Routes
|--------------------------------------------------------------------------
|
| Implementação completa dos endpoints OAuth2/OIDC conforme as especificações:
| - RFC 6749 (OAuth 2.0)
| - RFC 6750 (Bearer Token)
| - OpenID Connect Core 1.0
| - RFC 8414 (Authorization Server Metadata)
|
*/

// ===== OIDC Discovery Endpoints =====

/**
 * OpenID Connect Discovery Document
 * https://auth.wmj.com.br/.well-known/openid_configuration
 */
Route::get('/.well-known/openid-configuration', [DiscoveryController::class, 'openidConfiguration'])
    ->name('oauth.oidc.discovery');

/**
 * JSON Web Key Set (JWKS)
 * https://auth.wmj.com.br/.well-known/jwks.json
 */
Route::get('/.well-known/jwks.json', [DiscoveryController::class, 'jwks'])
    ->name('oauth.jwks');

/**
 * OAuth 2.0 Authorization Server Metadata (RFC 8414)
 * https://auth.wmj.com.br/.well-known/oauth-authorization-server
 */
Route::get('/.well-known/oauth-authorization-server', [DiscoveryController::class, 'oauth2Metadata'])
    ->name('oauth.metadata');

// ===== Core OAuth 2.0 Endpoints =====

/**
 * Authorization Endpoint (RFC 6749 Section 3.1)
 * GET  /oauth/authorize - Show authorization page
 * POST /oauth/authorize - Process authorization decision
 * 
 * Suporta authorization code flow com PKCE opcional
 */
Route::get('/oauth/authorize', [AuthorizationController::class, 'authorize'])
    ->middleware(['web', 'auth'])
    ->name('oauth.authorize');

Route::post('/oauth/authorize', [AuthorizationController::class, 'approve'])
    ->middleware(['web', 'auth'])
    ->name('oauth.approve');

// ===== API Endpoints movidos para routes/oauth-api.php =====

// ===== Administrative Endpoints =====

/**
 * Health Check
 * GET /oauth/health - System health status
 */
Route::get('/oauth/health', [DiscoveryController::class, 'health'])
    ->name('oauth.health');

/**
 * Token Introspection (RFC 7662) - Future implementation
 * POST /oauth/introspect - Token introspection for resource servers
 */

// ===== Development/Debug Routes (apenas em ambiente local) =====

if (app()->environment(['local', 'development'])) {
    /**
     * Debug endpoints - apenas em desenvolvimento
     */
    Route::prefix('oauth/debug')->group(function () {
        Route::get('/config', function () {
            return response()->json([
                'issuer' => config('oauth.issuer'),
                'scopes' => config('oauth.scopes'),
                'token_lifetimes' => [
                    'access_token' => config('oauth.access_token_lifetime'),
                    'refresh_token' => config('oauth.refresh_token_lifetime'),
                    'auth_code' => config('oauth.authorization_code_lifetime'),
                ],
                'keys_exist' => [
                    'private' => file_exists(config('oauth.private_key')),
                    'public' => file_exists(config('oauth.public_key')),
                ],
            ]);
        })->name('oauth.debug.config');
        
        Route::get('/test-jwt', function () {
            try {
                $jwtService = app(\App\Services\OAuth\JwtService::class);
                $user = \App\Models\User::first();
                
                if (!$user) {
                    return response()->json(['error' => 'No users found']);
                }
                
                $token = $jwtService->createIdToken($user, 'test-client', ['openid', 'profile', 'email']);
                $decoded = $jwtService->verifyToken($token);
                
                return response()->json([
                    'token' => $token,
                    'decoded' => $decoded,
                    'status' => 'success'
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'error' => $e->getMessage(),
                    'status' => 'failed'
                ], 500);
            }
        })->name('oauth.debug.jwt');
    });
}