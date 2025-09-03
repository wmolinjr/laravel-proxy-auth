<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OAuth2 Server Configuration
    |--------------------------------------------------------------------------
    */
    
    'private_key' => env('OAUTH_PRIVATE_KEY_PATH', storage_path('oauth-private.key')),
    'public_key' => env('OAUTH_PUBLIC_KEY_PATH', storage_path('oauth-public.key')),
    'encryption_key' => env('OAUTH_ENCRYPTION_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Token Lifetimes
    |--------------------------------------------------------------------------
    | 
    | Formatos de duração ISO 8601:
    | PT1H = 1 hora
    | PT10M = 10 minutos  
    | P1M = 1 mês
    | P1D = 1 dia
    */
    
    'access_token_lifetime' => env('OAUTH_ACCESS_TOKEN_LIFETIME', 'PT1H'),
    'refresh_token_lifetime' => env('OAUTH_REFRESH_TOKEN_LIFETIME', 'P1M'),
    'authorization_code_lifetime' => env('OAUTH_AUTH_CODE_LIFETIME', 'PT10M'),

    /*
    |--------------------------------------------------------------------------
    | OIDC Configuration
    |--------------------------------------------------------------------------
    */
    
    'issuer' => env('OAUTH_ISSUER', config('app.url')),
    'jwks_uri' => env('OAUTH_JWKS_URI', config('app.url') . '/.well-known/jwks.json'),

    /*
    |--------------------------------------------------------------------------
    | Supported Scopes
    |--------------------------------------------------------------------------
    */
    
    'scopes' => [
        'openid' => 'OpenID Connect identifier',
        'profile' => 'Access to profile information (name, username)',
        'email' => 'Access to email address and verification status',
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported Claims per Scope
    |--------------------------------------------------------------------------
    */
    
    'claims' => [
        'profile' => ['name', 'preferred_username'],
        'email' => ['email', 'email_verified'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Client for Testing
    |--------------------------------------------------------------------------
    */
    
    'default_client' => [
        'id' => env('OAUTH_DEFAULT_CLIENT_ID'),
        'secret' => env('OAUTH_DEFAULT_CLIENT_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Apache Studio Client Configuration
    |--------------------------------------------------------------------------
    */
    
    'apache_studio_client' => [
        'secret' => env('APACHE_STUDIO_CLIENT_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    */
    
    'rate_limit' => [
        'requests_per_minute' => 60,
        'key_prefix' => 'oauth:',
    ],

    /*
    |--------------------------------------------------------------------------
    | Clock Skew Tolerance
    |--------------------------------------------------------------------------
    | 
    | Tolerance for clock skew between servers (in seconds)
    | Default: 300 seconds (5 minutes)
    */
    
    'clock_skew_tolerance' => env('OAUTH_CLOCK_SKEW_TOLERANCE', 300),
];