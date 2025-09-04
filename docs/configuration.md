# ⚙️ Configuration Guide

This guide covers all configuration options available in the Laravel OAuth2/OIDC Identity Provider.

## Configuration Files

### Main Configuration Files
- `config/oauth.php` - OAuth2/OIDC specific settings
- `config/app.php` - Application settings
- `config/database.php` - Database connections
- `config/queue.php` - Queue configurations
- `config/mail.php` - Email settings
- `config/cache.php` - Caching configurations

## OAuth2 Configuration

### Basic Settings

```php
// config/oauth.php
return [
    // Token Lifetimes (ISO 8601 Duration Format)
    'token_lifetime' => [
        'access_token' => env('OAUTH_ACCESS_TOKEN_LIFETIME', 'PT1H'),    // 1 hour
        'refresh_token' => env('OAUTH_REFRESH_TOKEN_LIFETIME', 'P1M'),   // 1 month
        'auth_code' => env('OAUTH_AUTH_CODE_LIFETIME', 'PT10M'),         // 10 minutes
    ],

    // Key Paths
    'keys' => [
        'private' => env('OAUTH_PRIVATE_KEY_PATH', storage_path('oauth-private.key')),
        'public' => env('OAUTH_PUBLIC_KEY_PATH', storage_path('oauth-public.key')),
        'passphrase' => env('OAUTH_PASSPHRASE', null),
    ],

    // Security Settings
    'security' => [
        'require_pkce' => env('OAUTH_REQUIRE_PKCE', true),
        'enable_implicit_grant' => env('OAUTH_ENABLE_IMPLICIT_GRANT', false),
        'enable_password_grant' => env('OAUTH_ENABLE_PASSWORD_GRANT', false),
    ],
];
```

### Scopes Configuration

```php
// config/oauth.php
'scopes' => [
    'openid' => [
        'name' => 'OpenID Connect',
        'description' => 'Access to OpenID Connect identity',
        'required' => true,
    ],
    'profile' => [
        'name' => 'Profile Information',
        'description' => 'Access to user profile information',
        'claims' => ['name', 'family_name', 'given_name', 'picture'],
    ],
    'email' => [
        'name' => 'Email Address',
        'description' => 'Access to user email address',
        'claims' => ['email', 'email_verified'],
    ],
    'phone' => [
        'name' => 'Phone Number',
        'description' => 'Access to user phone number',
        'claims' => ['phone_number', 'phone_number_verified'],
    ],
    'address' => [
        'name' => 'Address Information',
        'description' => 'Access to user address information',
        'claims' => ['address'],
    ],
    'admin' => [
        'name' => 'Administrative Access',
        'description' => 'Full administrative access to the system',
        'restricted' => true,
    ],
],
```

### Client Configuration

```php
// config/oauth.php
'clients' => [
    'default_redirect_uri' => env('OAUTH_DEFAULT_REDIRECT_URI', 'http://localhost:3000/callback'),
    
    'allow_plain_text_pkce' => env('OAUTH_ALLOW_PLAIN_TEXT_PKCE', false),
    
    'grant_types' => [
        'authorization_code' => true,
        'client_credentials' => true,
        'refresh_token' => true,
        'implicit' => false,          // Not recommended
        'password' => false,          // Not recommended
    ],
],
```

## Metrics and Monitoring

### Metrics Configuration

```php
// config/oauth.php
'metrics' => [
    'enabled' => env('OAUTH_ENABLE_METRICS', true),
    'queue' => env('OAUTH_METRICS_QUEUE', 'metrics'),
    'retention_days' => env('OAUTH_METRICS_RETENTION_DAYS', 30),
    
    'endpoints' => [
        'token' => true,
        'authorize' => true,
        'userinfo' => true,
        'introspect' => true,
        'discovery' => false,
        'jwks' => false,
    ],
    
    'collect' => [
        'response_time' => true,
        'client_id' => true,
        'user_id' => true,
        'scopes' => true,
        'ip_address' => true,
        'user_agent' => false,
        'metadata' => true,
    ],
],
```

### Alert Configuration

```php
// config/oauth.php
'alerts' => [
    'enabled' => env('OAUTH_ENABLE_ALERTS', true),
    'channels' => ['mail', 'slack'],
    
    'thresholds' => [
        'response_time' => [
            'warning' => env('OAUTH_ALERT_RESPONSE_TIME_WARNING', 500),   // ms
            'critical' => env('OAUTH_ALERT_RESPONSE_TIME_CRITICAL', 1000), // ms
        ],
        'error_rate' => [
            'warning' => env('OAUTH_ALERT_ERROR_RATE_WARNING', 5.0),     // %
            'critical' => env('OAUTH_ALERT_ERROR_RATE_CRITICAL', 10.0),   // %
        ],
        'failed_authentications' => [
            'warning' => env('OAUTH_ALERT_FAILED_AUTH_WARNING', 10),     // count
            'critical' => env('OAUTH_ALERT_FAILED_AUTH_CRITICAL', 25),    // count
        ],
        'suspicious_activity' => [
            'multiple_failed_attempts' => 5,
            'rate_limit_exceeded' => 3,
            'invalid_client_attempts' => 10,
        ],
    ],
    
    'cooldown' => [
        'warning' => env('OAUTH_ALERT_COOLDOWN_WARNING', 300),    // 5 minutes
        'critical' => env('OAUTH_ALERT_COOLDOWN_CRITICAL', 600),   // 10 minutes
    ],
],
```

## Database Configuration

### Connection Settings

```php
// config/database.php
'connections' => [
    'mysql' => [
        'driver' => 'mysql',
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '3306'),
        'database' => env('DB_DATABASE', 'oauth_provider'),
        'username' => env('DB_USERNAME', 'forge'),
        'password' => env('DB_PASSWORD', ''),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'options' => [
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_EMULATE_PREPARES => false,
        ],
    ],
    
    'pgsql' => [
        'driver' => 'pgsql',
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '5432'),
        'database' => env('DB_DATABASE', 'oauth_provider'),
        'username' => env('DB_USERNAME', 'forge'),
        'password' => env('DB_PASSWORD', ''),
        'charset' => 'utf8',
        'search_path' => 'public',
    ],
],
```

### Connection Pooling (Production)

```env
# MySQL
DB_POOL_MIN_CONNECTIONS=2
DB_POOL_MAX_CONNECTIONS=20

# PostgreSQL
DB_POOL_MIN_CONNECTIONS=5
DB_POOL_MAX_CONNECTIONS=30
```

## Caching Configuration

### Cache Stores

```php
// config/cache.php
'stores' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'cache',
        'prefix' => env('CACHE_PREFIX', 'oauth_cache'),
    ],
    
    'memcached' => [
        'driver' => 'memcached',
        'servers' => [
            ['host' => '127.0.0.1', 'port' => 11211, 'weight' => 100],
        ],
    ],
],

// Cache Configuration for OAuth
'oauth_cache' => [
    'discovery_ttl' => 3600,        // 1 hour
    'jwks_ttl' => 3600,             // 1 hour
    'client_ttl' => 1800,           // 30 minutes
    'user_ttl' => 900,              // 15 minutes
],
```

### Redis Configuration

```env
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Separate Redis databases
REDIS_CACHE_DB=0
REDIS_SESSION_DB=1
REDIS_QUEUE_DB=2
```

## Queue Configuration

### Queue Connections

```php
// config/queue.php
'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => env('REDIS_QUEUE', 'default'),
        'retry_after' => 90,
        'block_for' => null,
    ],
    
    'database' => [
        'driver' => 'database',
        'table' => 'jobs',
        'queue' => 'default',
        'retry_after' => 90,
    ],
],

// Queue-specific settings
'oauth_queues' => [
    'metrics' => [
        'connection' => 'redis',
        'queue' => 'metrics',
        'workers' => 2,
        'timeout' => 60,
        'retry_after' => 90,
        'max_tries' => 3,
    ],
    'notifications' => [
        'connection' => 'redis',
        'queue' => 'notifications',
        'workers' => 1,
        'timeout' => 30,
    ],
],
```

## Security Configuration

### Rate Limiting

```php
// config/oauth.php
'rate_limiting' => [
    'enabled' => env('OAUTH_RATE_LIMITING_ENABLED', true),
    
    'limits' => [
        'oauth/token' => [
            'requests' => 60,
            'per_minutes' => 1,
            'by' => 'ip',              // 'ip', 'user', 'client'
        ],
        'oauth/authorize' => [
            'requests' => 120,
            'per_minutes' => 1,
            'by' => 'ip',
        ],
        'oauth/userinfo' => [
            'requests' => 300,
            'per_minutes' => 1,
            'by' => 'user',
        ],
        'oauth/introspect' => [
            'requests' => 180,
            'per_minutes' => 1,
            'by' => 'client',
        ],
    ],
    
    'blocked_response' => [
        'error' => 'rate_limit_exceeded',
        'error_description' => 'Too many requests. Please try again later.',
        'retry_after' => 60,
    ],
],
```

### CORS Settings

```php
// config/cors.php
return [
    'paths' => [
        'oauth/*',
        '.well-known/*',
        'api/*',
    ],
    
    'allowed_methods' => ['GET', 'POST', 'OPTIONS'],
    
    'allowed_origins' => env('CORS_ALLOWED_ORIGINS', '*'),
    
    'allowed_origins_patterns' => [],
    
    'allowed_headers' => ['Content-Type', 'Authorization', 'Accept'],
    
    'exposed_headers' => [],
    
    'max_age' => 86400,
    
    'supports_credentials' => true,
];
```

### Security Headers

```php
// config/oauth.php
'security_headers' => [
    'strict_transport_security' => 'max-age=31536000; includeSubDomains',
    'x_content_type_options' => 'nosniff',
    'x_frame_options' => 'SAMEORIGIN',
    'referrer_policy' => 'strict-origin-when-cross-origin',
    'content_security_policy' => "default-src 'self'; script-src 'self' 'unsafe-eval'; style-src 'self' 'unsafe-inline'",
],
```

## Mail Configuration

### SMTP Settings

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@yourdomain.com"
MAIL_FROM_NAME="OAuth Provider"
```

### Mail Templates

```php
// config/oauth.php
'mail' => [
    'templates' => [
        'alert' => 'emails.oauth.alert',
        'client_created' => 'emails.oauth.client-created',
        'password_reset' => 'emails.oauth.password-reset',
    ],
    
    'from' => [
        'address' => env('MAIL_FROM_ADDRESS'),
        'name' => env('MAIL_FROM_NAME'),
    ],
    
    'reply_to' => [
        'address' => env('MAIL_REPLY_TO_ADDRESS'),
        'name' => env('MAIL_REPLY_TO_NAME'),
    ],
],
```

## Logging Configuration

### Log Channels

```php
// config/logging.php
'channels' => [
    'oauth' => [
        'driver' => 'daily',
        'path' => storage_path('logs/oauth.log'),
        'level' => env('LOG_LEVEL', 'info'),
        'days' => 14,
    ],
    
    'oauth_audit' => [
        'driver' => 'daily',
        'path' => storage_path('logs/oauth-audit.log'),
        'level' => 'info',
        'days' => 90,        // Keep audit logs longer
    ],
    
    'oauth_metrics' => [
        'driver' => 'daily',
        'path' => storage_path('logs/oauth-metrics.log'),
        'level' => 'debug',
        'days' => 7,
    ],
],
```

## Environment Variables Reference

### Core Settings
```env
# Application
APP_NAME="OAuth Provider"
APP_ENV=production
APP_KEY=base64:your-app-key
APP_DEBUG=false
APP_URL=https://oauth.yourdomain.com

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=oauth_provider
DB_USERNAME=oauth_user
DB_PASSWORD=secure_password

# OAuth2
OAUTH_PRIVATE_KEY_PATH="storage/oauth-private.key"
OAUTH_PUBLIC_KEY_PATH="storage/oauth-public.key"
OAUTH_PASSPHRASE=null
OAUTH_ACCESS_TOKEN_LIFETIME="PT1H"
OAUTH_REFRESH_TOKEN_LIFETIME="P1M"
OAUTH_AUTH_CODE_LIFETIME="PT10M"
OAUTH_REQUIRE_PKCE=true

# Metrics & Monitoring
OAUTH_ENABLE_METRICS=true
OAUTH_ENABLE_ALERTS=true
OAUTH_METRICS_RETENTION_DAYS=30

# Security
OAUTH_RATE_LIMITING_ENABLED=true
CORS_ALLOWED_ORIGINS="https://yourapp.com,https://anotherapp.com"

# Caching
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Mail
MAIL_MAILER=smtp
MAIL_HOST=smtp.yourdomain.com
MAIL_PORT=587
MAIL_USERNAME=oauth@yourdomain.com
MAIL_PASSWORD=mail_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="oauth@yourdomain.com"
MAIL_FROM_NAME="OAuth Provider"
```

## Configuration Validation

### Validate Configuration

```bash
# Check OAuth configuration
php artisan oauth:config-check

# Validate environment
php artisan config:validate

# Test connections
php artisan oauth:test-connections
```

### Configuration Commands

```bash
# Clear configuration cache
php artisan config:clear

# Cache configuration for performance
php artisan config:cache

# Show current configuration
php artisan config:show oauth
```

## Performance Tuning

### Production Optimizations

```bash
# Optimize autoloader
composer install --optimize-autoloader --no-dev

# Cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Optimize Laravel
php artisan optimize

# OPcache settings (php.ini)
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.revalidate_freq=0
opcache.validate_timestamps=0
```

### Database Optimizations

```sql
-- Add indexes for OAuth tables
ALTER TABLE oauth_access_tokens ADD INDEX idx_expires_at (expires_at);
ALTER TABLE oauth_refresh_tokens ADD INDEX idx_expires_at (expires_at);
ALTER TABLE oauth_authorization_codes ADD INDEX idx_expires_at (expires_at);

-- Add indexes for metrics
ALTER TABLE oauth_metrics ADD INDEX idx_endpoint_created (endpoint, created_at);
ALTER TABLE oauth_metrics ADD INDEX idx_client_created (client_id, created_at);
```

## Next Steps

After configuring your OAuth provider:

1. [🔧 Setup OAuth2 clients](oauth2-setup.md)
2. [📊 Configure monitoring](monitoring.md)
3. [🛡️ Review security settings](security.md)
4. [🚀 Prepare for deployment](deployment.md)