<?php

use App\Http\Controllers\OAuth\TokenController;
use App\Http\Controllers\OAuth\UserInfoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| OAuth API Routes (sem CSRF)
|--------------------------------------------------------------------------
|
| Estas rotas são para endpoints OAuth que não devem ter verificação CSRF
| conforme as especificações RFC 6749 e OpenID Connect
|
*/

/**
 * Token Endpoint (RFC 6749 Section 3.2)
 * POST /oauth/token - Exchange authorization code for tokens
 */
Route::post('/oauth/token', [TokenController::class, 'issueToken'])
    ->name('oauth.token');

/**
 * UserInfo Endpoint (OIDC Core Section 5.3)
 * GET/POST /oauth/userinfo - Get user information
 */
Route::match(['GET', 'POST'], '/oauth/userinfo', [UserInfoController::class, 'userInfo'])
    ->name('oauth.userinfo');

/**
 * Token Introspection (RFC 7662)
 * POST /oauth/introspect - Token introspection for resource servers
 */
Route::post('/oauth/introspect', [UserInfoController::class, 'introspect'])
    ->name('oauth.introspect');