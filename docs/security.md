# 🛡️ Security Guide

This comprehensive security guide covers all aspects of securing your Laravel OAuth2/OIDC Identity Provider.

## Security Architecture

### Defense in Depth

The OAuth provider implements multiple layers of security:

1. **Network Layer**: HTTPS, firewall, DDoS protection
2. **Application Layer**: Input validation, output encoding, CSRF protection
3. **Authentication Layer**: Strong password policies, MFA, account lockout
4. **Authorization Layer**: RBAC, scope-based permissions, JWT validation
5. **Data Layer**: Encryption at rest, secure key storage, database security
6. **Monitoring Layer**: Real-time alerts, audit logging, anomaly detection

## OAuth2 Security

### PKCE (Proof Key for Code Exchange)

PKCE is mandatory for all OAuth flows to prevent authorization code interception attacks:

```php
// config/oauth.php
'security' => [
    'require_pkce' => true,  // Mandatory PKCE
    'allow_plain_text_pkce' => false,  // Only S256 method allowed
],
```

#### PKCE Implementation
```javascript
// Client-side PKCE implementation
class PKCEGenerator {
    static generateCodeVerifier() {
        const array = new Uint8Array(32);
        crypto.getRandomValues(array);
        return base64URLEncode(array);
    }
    
    static async generateCodeChallenge(codeVerifier) {
        const encoder = new TextEncoder();
        const data = encoder.encode(codeVerifier);
        const digest = await crypto.subtle.digest('SHA-256', data);
        return base64URLEncode(new Uint8Array(digest));
    }
}

// Server-side validation
public function validatePKCE(string $codeVerifier, string $codeChallenge): bool
{
    $hash = hash('sha256', $codeVerifier, true);
    $calculatedChallenge = rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
    
    return hash_equals($codeChallenge, $calculatedChallenge);
}
```

### Token Security

#### JWT Configuration
```php
// Secure JWT configuration
'jwt' => [
    'algorithm' => 'RS256',  // Asymmetric signing only
    'key_size' => 4096,      // Large key size
    'issuer' => env('APP_URL'),
    'audience' => env('APP_URL'),
    'leeway' => 60,          // Clock skew tolerance
],
```

#### Token Lifetimes
```php
// Short-lived tokens for security
'token_lifetime' => [
    'access_token' => 'PT1H',    // 1 hour
    'refresh_token' => 'P30D',   // 30 days (with rotation)
    'auth_code' => 'PT10M',      // 10 minutes
    'id_token' => 'PT1H',        // 1 hour
],
```

#### Refresh Token Rotation
```php
class RefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    public function persistNewRefreshToken(RefreshTokenEntityInterface $refreshTokenEntity): void
    {
        // Rotate refresh tokens on each use
        $oldTokenId = request()->input('refresh_token');
        
        if ($oldTokenId) {
            // Revoke old refresh token
            OAuthRefreshToken::where('id', $oldTokenId)->update(['revoked' => true]);
            
            // Log token rotation
            OAuthAuditService::logTokenRotated($oldTokenId, $refreshTokenEntity->getIdentifier());
        }
        
        // Create new refresh token
        OAuthRefreshToken::create([
            'id' => $refreshTokenEntity->getIdentifier(),
            'access_token_id' => $refreshTokenEntity->getAccessToken()->getIdentifier(),
            'revoked' => false,
            'expires_at' => $refreshTokenEntity->getExpiryDateTime(),
        ]);
    }
}
```

### Client Security

#### Client Authentication Methods
```php
// Supported client authentication methods
'client_authentication' => [
    'client_secret_basic' => true,   // HTTP Basic Auth
    'client_secret_post' => true,    // Form POST
    'client_secret_jwt' => true,     // JWT with shared secret
    'private_key_jwt' => true,       // JWT with private key
    'none' => false,                 // Public clients only
],
```

#### Dynamic Client Registration Security
```php
class ClientRegistrationController extends Controller
{
    public function register(Request $request)
    {
        // Validate registration request
        $this->validateRegistration($request);
        
        // Check rate limits
        RateLimiter::hit('client-registration:' . $request->ip(), 600); // 10 minutes
        
        // Generate secure client credentials
        $client = new OAuthClient([
            'client_id' => $this->generateSecureClientId(),
            'client_secret' => $this->generateSecureClientSecret(),
            'name' => $request->input('client_name'),
            'redirect_uris' => $this->validateRedirectUris($request->input('redirect_uris')),
            'scopes' => $this->filterAllowedScopes($request->input('scope')),
            'is_confidential' => $this->determineClientType($request),
            'require_pkce' => true,
        ]);
        
        $client->save();
        
        // Audit log
        OAuthAuditService::logClientRegistered($client->client_id, $request->ip());
        
        return response()->json([
            'client_id' => $client->client_id,
            'client_secret' => $client->client_secret,
            'client_id_issued_at' => time(),
            'client_secret_expires_at' => 0, // Never expires
        ]);
    }
    
    private function generateSecureClientId(): string
    {
        return 'client_' . Str::random(32);
    }
    
    private function generateSecureClientSecret(): string
    {
        return hash('sha256', Str::random(64));
    }
    
    private function validateRedirectUris(array $uris): array
    {
        $validated = [];
        
        foreach ($uris as $uri) {
            // Validate URI format
            if (!filter_var($uri, FILTER_VALIDATE_URL)) {
                throw new InvalidArgumentException('Invalid redirect URI: ' . $uri);
            }
            
            // Ensure HTTPS in production
            if (app()->environment('production') && !str_starts_with($uri, 'https://')) {
                throw new InvalidArgumentException('HTTPS required for redirect URIs in production');
            }
            
            // Prevent localhost in production
            if (app()->environment('production') && str_contains($uri, 'localhost')) {
                throw new InvalidArgumentException('localhost not allowed in production');
            }
            
            $validated[] = $uri;
        }
        
        return $validated;
    }
}
```

## Rate Limiting

### Endpoint-Specific Rate Limits

```php
// config/oauth.php - rate limiting
'rate_limiting' => [
    'enabled' => true,
    'store' => 'redis', // Use Redis for distributed rate limiting
    
    'limits' => [
        // Token endpoint - most critical
        'oauth/token' => [
            'requests' => 60,
            'per_minutes' => 1,
            'by' => ['ip', 'client_id'], // Combined limiting
        ],
        
        // Authorization endpoint
        'oauth/authorize' => [
            'requests' => 120,
            'per_minutes' => 1,
            'by' => 'ip',
        ],
        
        // UserInfo endpoint
        'oauth/userinfo' => [
            'requests' => 300,
            'per_minutes' => 1,
            'by' => 'user_id',
        ],
        
        // Token introspection
        'oauth/introspect' => [
            'requests' => 180,
            'per_minutes' => 1,
            'by' => 'client_id',
        ],
        
        // Client registration
        'oauth/clients' => [
            'requests' => 5,
            'per_minutes' => 60, // 5 per hour
            'by' => 'ip',
        ],
    ],
    
    // Progressive penalties
    'penalties' => [
        'soft_ban' => [
            'violations' => 3,
            'duration_minutes' => 60,
        ],
        'hard_ban' => [
            'violations' => 10,
            'duration_minutes' => 1440, // 24 hours
        ],
    ],
],
```

### Advanced Rate Limiting

```php
class OAuthRateLimitMiddleware
{
    public function handle($request, Closure $next)
    {
        $key = $this->resolveRateLimitKey($request);
        $limit = $this->getRateLimit($request);
        
        // Check current usage
        $attempts = RateLimiter::attempts($key);
        
        if ($attempts >= $limit['requests']) {
            // Log rate limit violation
            OAuthAuditService::logRateLimitExceeded(
                $this->getClientId($request),
                $request->path(),
                $request->ip()
            );
            
            // Return rate limit response
            return response()->json([
                'error' => 'rate_limit_exceeded',
                'error_description' => 'Too many requests',
                'retry_after' => RateLimiter::availableIn($key),
            ], 429);
        }
        
        // Increment counter
        RateLimiter::hit($key, $limit['per_minutes'] * 60);
        
        $response = $next($request);
        
        // Add rate limit headers
        return $response->withHeaders([
            'X-RateLimit-Limit' => $limit['requests'],
            'X-RateLimit-Remaining' => max(0, $limit['requests'] - $attempts - 1),
            'X-RateLimit-Reset' => time() + RateLimiter::availableIn($key),
        ]);
    }
}
```

## Input Validation & Sanitization

### Request Validation

```php
class TokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
    
    public function rules(): array
    {
        return [
            'grant_type' => 'required|string|in:authorization_code,refresh_token,client_credentials',
            'client_id' => 'required|string|max:100|regex:/^[a-zA-Z0-9_-]+$/',
            'client_secret' => 'nullable|string|max:255',
            'code' => 'required_if:grant_type,authorization_code|string|max:1000',
            'redirect_uri' => 'required_if:grant_type,authorization_code|url|max:2000',
            'refresh_token' => 'required_if:grant_type,refresh_token|string|max:1000',
            'code_verifier' => 'nullable|string|min:43|max:128|regex:/^[a-zA-Z0-9._~-]+$/',
            'scope' => 'nullable|string|max:1000',
        ];
    }
    
    public function messages(): array
    {
        return [
            'client_id.regex' => 'Client ID contains invalid characters',
            'code_verifier.regex' => 'Code verifier contains invalid characters',
            'redirect_uri.url' => 'Redirect URI must be a valid URL',
        ];
    }
    
    protected function prepareForValidation(): void
    {
        // Normalize input
        if ($this->has('scope')) {
            $this->merge([
                'scope' => trim($this->input('scope')),
            ]);
        }
    }
}
```

### SQL Injection Prevention

```php
// Use Eloquent ORM and parameter binding
class OAuthAccessTokenRepository
{
    public function findValidToken(string $tokenId): ?OAuthAccessToken
    {
        // Safe: Uses parameter binding
        return OAuthAccessToken::where('id', $tokenId)
                              ->where('revoked', false)
                              ->where('expires_at', '>', now())
                              ->first();
    }
    
    public function revokeTokensByClient(string $clientId): int
    {
        // Safe: Uses parameter binding
        return OAuthAccessToken::where('client_id', $clientId)
                              ->update(['revoked' => true]);
    }
}
```

## HTTPS and Transport Security

### HTTPS Configuration

```apache
# Apache HTTPS configuration
<VirtualHost *:443>
    ServerName oauth-provider.com
    DocumentRoot /path/to/oauth-provider/public
    
    SSLEngine on
    SSLCertificateFile /path/to/certificate.crt
    SSLCertificateKeyFile /path/to/private.key
    SSLCertificateChainFile /path/to/ca-bundle.crt
    
    # Security headers
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-eval'; style-src 'self' 'unsafe-inline'"
    
    # OCSP Stapling
    SSLUseStapling on
    SSLStaplingCache "shmcb:logs/stapling-cache(150000)"
</VirtualHost>

# Redirect HTTP to HTTPS
<VirtualHost *:80>
    ServerName oauth-provider.com
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]
</VirtualHost>
```

### TLS Configuration

```nginx
# Nginx HTTPS configuration
server {
    listen 443 ssl http2;
    server_name oauth-provider.com;
    
    ssl_certificate /path/to/certificate.crt;
    ssl_certificate_key /path/to/private.key;
    ssl_trusted_certificate /path/to/ca-bundle.crt;
    
    # TLS configuration
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512:ECDHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;
    
    # OCSP stapling
    ssl_stapling on;
    ssl_stapling_verify on;
    
    # Security headers
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
}
```

## Cryptographic Security

### Key Management

```php
class CryptographicKeyManager
{
    private const KEY_ROTATION_DAYS = 90;
    
    public function generateKeyPair(): array
    {
        $config = [
            'private_key_bits' => 4096,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'digest_alg' => 'sha256',
        ];
        
        $resource = openssl_pkey_new($config);
        
        if (!$resource) {
            throw new RuntimeException('Failed to generate key pair: ' . openssl_error_string());
        }
        
        openssl_pkey_export($resource, $privateKey);
        $publicKey = openssl_pkey_get_details($resource)['key'];
        
        return [$privateKey, $publicKey];
    }
    
    public function rotateKeys(): void
    {
        $currentKeyAge = $this->getCurrentKeyAge();
        
        if ($currentKeyAge >= self::KEY_ROTATION_DAYS) {
            [$newPrivateKey, $newPublicKey] = $this->generateKeyPair();
            
            // Store new keys
            Storage::disk('secure')->put('oauth-private-new.key', $newPrivateKey);
            Storage::disk('secure')->put('oauth-public-new.key', $newPublicKey);
            
            // Update configuration
            $this->updateKeyPaths();
            
            // Notify administrators
            Notification::route('mail', config('oauth.admin_email'))
                       ->notify(new KeyRotationNotification());
            
            // Audit log
            OAuthAuditService::logKeyRotation();
        }
    }
    
    private function getCurrentKeyAge(): int
    {
        $keyPath = storage_path('oauth-private.key');
        
        if (!file_exists($keyPath)) {
            return PHP_INT_MAX; // Force key generation
        }
        
        $keyTime = filemtime($keyPath);
        return (time() - $keyTime) / (24 * 60 * 60); // Days
    }
}
```

### Secure Random Generation

```php
class SecureRandomGenerator
{
    public static function generateToken(int $length = 32): string
    {
        try {
            $bytes = random_bytes($length);
            return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
        } catch (Exception $e) {
            // Fallback to openssl if random_bytes fails
            $bytes = openssl_random_pseudo_bytes($length, $strong);
            
            if (!$strong) {
                throw new RuntimeException('Unable to generate cryptographically strong random bytes');
            }
            
            return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
        }
    }
    
    public static function generateState(): string
    {
        return self::generateToken(32);
    }
    
    public static function generateNonce(): string
    {
        return self::generateToken(32);
    }
    
    public static function generateClientSecret(): string
    {
        return hash('sha256', self::generateToken(64));
    }
}
```

## Database Security

### Connection Security

```php
// config/database.php
'mysql' => [
    'driver' => 'mysql',
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '3306'),
    'database' => env('DB_DATABASE', 'oauth_provider'),
    'username' => env('DB_USERNAME', 'oauth_user'),
    'password' => env('DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'options' => [
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
        PDO::MYSQL_ATTR_SSL_CA => env('DB_SSL_CA'),
        PDO::MYSQL_ATTR_SSL_CERT => env('DB_SSL_CERT'),
        PDO::MYSQL_ATTR_SSL_KEY => env('DB_SSL_KEY'),
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => true,
    ],
],
```

### Encryption at Rest

```php
// Encrypt sensitive database fields
class OAuthClient extends Model
{
    protected $fillable = [
        'name',
        'client_id',
        'client_secret',
        'redirect_uris',
        'scopes',
        'is_confidential',
    ];
    
    protected $casts = [
        'redirect_uris' => 'encrypted:array',
        'scopes' => 'encrypted:array',
        'is_confidential' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    // Encrypt client secret
    public function setClientSecretAttribute($value): void
    {
        $this->attributes['client_secret'] = encrypt($value);
    }
    
    public function getClientSecretAttribute($value): ?string
    {
        return $value ? decrypt($value) : null;
    }
}
```

## Session Security

### Secure Session Configuration

```php
// config/session.php
return [
    'driver' => env('SESSION_DRIVER', 'redis'),
    'lifetime' => env('SESSION_LIFETIME', 120),
    'expire_on_close' => true,
    'encrypt' => true,
    'files' => storage_path('framework/sessions'),
    'connection' => env('SESSION_CONNECTION', 'default'),
    'table' => 'sessions',
    'store' => env('SESSION_STORE', 'default'),
    'lottery' => [2, 100],
    'cookie' => env('SESSION_COOKIE', Str::slug(env('APP_NAME', 'laravel'), '_').'_session'),
    'path' => '/',
    'domain' => env('SESSION_DOMAIN'),
    'secure' => env('SESSION_SECURE_COOKIE', true),
    'http_only' => true,
    'same_site' => 'lax',
];
```

### Session Fixation Prevention

```php
class AuthController extends Controller
{
    public function login(Request $request)
    {
        // Validate credentials
        if (Auth::attempt($request->only('email', 'password'))) {
            // Regenerate session ID to prevent fixation
            $request->session()->regenerate();
            
            // Log successful authentication
            OAuthAuditService::logSuccessfulAuthentication(
                Auth::id(),
                $request->ip(),
                $request->userAgent()
            );
            
            return redirect()->intended('/dashboard');
        }
        
        // Log failed authentication
        OAuthAuditService::logFailedAuthentication(
            $request->input('email'),
            'invalid_credentials',
            $request->ip()
        );
        
        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ]);
    }
}
```

## CORS Security

### Secure CORS Configuration

```php
// config/cors.php
return [
    'paths' => [
        'oauth/*',
        '.well-known/*',
        'api/*',
    ],
    
    'allowed_methods' => ['GET', 'POST', 'OPTIONS'],
    
    // Restrict origins in production
    'allowed_origins' => env('APP_ENV') === 'production' 
        ? explode(',', env('CORS_ALLOWED_ORIGINS', ''))
        : ['*'],
    
    'allowed_origins_patterns' => [],
    
    'allowed_headers' => [
        'Content-Type',
        'Authorization',
        'Accept',
        'X-Requested-With',
    ],
    
    'exposed_headers' => [
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
        'X-RateLimit-Reset',
    ],
    
    'max_age' => 86400, // 24 hours
    
    'supports_credentials' => true,
];
```

## Security Headers

### Comprehensive Security Headers

```php
class SecurityHeadersMiddleware
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);
        
        // HSTS
        $response->headers->set(
            'Strict-Transport-Security',
            'max-age=31536000; includeSubDomains; preload'
        );
        
        // Content Type Options
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        
        // Frame Options
        $response->headers->set('X-Frame-Options', 'DENY');
        
        // XSS Protection
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        
        // Referrer Policy
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        
        // Content Security Policy
        $csp = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: https:",
            "font-src 'self' https://fonts.gstatic.com",
            "connect-src 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "upgrade-insecure-requests",
        ];
        
        $response->headers->set('Content-Security-Policy', implode('; ', $csp));
        
        // Permissions Policy
        $permissions = [
            'camera=())',
            'microphone=()',
            'geolocation=()',
            'payment=()',
        ];
        
        $response->headers->set('Permissions-Policy', implode(', ', $permissions));
        
        return $response;
    }
}
```

## Vulnerability Scanning

### Automated Security Scanning

```bash
#!/bin/bash
# security-scan.sh

echo "Running OAuth Provider Security Scan..."

# Check for known vulnerabilities
echo "1. Checking for known vulnerabilities..."
composer audit

# Check for security updates
echo "2. Checking for security updates..."
npm audit

# Static analysis
echo "3. Running static analysis..."
php vendor/bin/phpstan analyse --level=8 app/

# Code quality check
echo "4. Running code quality checks..."
php vendor/bin/php-cs-fixer fix --dry-run --diff

# Check for secrets
echo "5. Scanning for secrets..."
git secrets --scan

# SSL/TLS check
echo "6. Checking SSL/TLS configuration..."
curl -I https://oauth-provider.com | grep -i "strict-transport-security"

# Headers check
echo "7. Verifying security headers..."
curl -I https://oauth-provider.com | grep -E "(X-Frame-Options|X-Content-Type-Options|Content-Security-Policy)"

echo "Security scan completed!"
```

### Dependency Scanning

```yaml
# .github/workflows/security.yml
name: Security Scan

on: [push, pull_request]

jobs:
  security:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v4
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: 8.3
        
    - name: Install dependencies
      run: composer install --no-dev
      
    - name: Run security audit
      run: composer audit
      
    - name: Run static analysis
      run: vendor/bin/phpstan analyse
      
    - name: Check for secrets
      uses: trufflesecurity/trufflehog@main
      with:
        path: ./
```

## Incident Response

### Security Incident Handling

```php
class SecurityIncidentHandler
{
    public function handleSecurityIncident(array $incident): void
    {
        // Log incident
        Log::channel('security')->critical('Security incident detected', $incident);
        
        // Determine severity
        $severity = $this->determineSeverity($incident);
        
        // Execute response based on severity
        match ($severity) {
            'critical' => $this->handleCriticalIncident($incident),
            'high' => $this->handleHighSeverityIncident($incident),
            'medium' => $this->handleMediumSeverityIncident($incident),
            'low' => $this->handleLowSeverityIncident($incident),
        };
        
        // Notify security team
        $this->notifySecurityTeam($incident, $severity);
        
        // Create incident ticket
        $this->createIncidentTicket($incident, $severity);
    }
    
    private function handleCriticalIncident(array $incident): void
    {
        // Immediate response actions
        
        if ($incident['type'] === 'mass_token_theft') {
            // Revoke all tokens for affected clients
            $this->revokeClientTokens($incident['client_id']);
        }
        
        if ($incident['type'] === 'brute_force_attack') {
            // Block attacking IP addresses
            $this->blockIPAddresses($incident['ip_addresses']);
        }
        
        if ($incident['type'] === 'data_breach') {
            // Enable emergency mode
            $this->enableEmergencyMode();
        }
        
        // Page on-call security engineer
        $this->pageSecurityEngineer($incident);
    }
    
    private function revokeClientTokens(string $clientId): void
    {
        DB::table('oauth_access_tokens')
          ->where('client_id', $clientId)
          ->update(['revoked' => true]);
          
        DB::table('oauth_refresh_tokens')
          ->whereIn('access_token_id', function($query) use ($clientId) {
              $query->select('id')
                    ->from('oauth_access_tokens')
                    ->where('client_id', $clientId);
          })
          ->update(['revoked' => true]);
          
        OAuthAuditService::logMassTokenRevocation($clientId, 'security_incident');
    }
    
    private function blockIPAddresses(array $ipAddresses): void
    {
        foreach ($ipAddresses as $ip) {
            RateLimiter::hit("security-block:{$ip}", 86400 * 30); // 30 days
            
            // Add to firewall block list
            $this->addToFirewallBlockList($ip);
        }
    }
    
    private function enableEmergencyMode(): void
    {
        // Temporarily disable new client registrations
        Cache::put('emergency_mode_enabled', true, 3600);
        
        // Increase rate limits
        Cache::put('emergency_rate_limits', true, 3600);
        
        // Require additional verification
        Cache::put('require_additional_auth', true, 3600);
    }
}
```

### Automated Threat Response

```php
class ThreatResponseSystem
{
    public function detectAndRespond(): void
    {
        $threats = $this->detectThreats();
        
        foreach ($threats as $threat) {
            $this->respondToThreat($threat);
        }
    }
    
    private function detectThreats(): array
    {
        $threats = [];
        
        // Detect brute force attacks
        $bruteForce = $this->detectBruteForceAttacks();
        if (!empty($bruteForce)) {
            $threats[] = [
                'type' => 'brute_force',
                'data' => $bruteForce,
                'severity' => 'high',
            ];
        }
        
        // Detect token abuse
        $tokenAbuse = $this->detectTokenAbuse();
        if (!empty($tokenAbuse)) {
            $threats[] = [
                'type' => 'token_abuse',
                'data' => $tokenAbuse,
                'severity' => 'medium',
            ];
        }
        
        // Detect anomalous client behavior
        $anomalous = $this->detectAnomalousClientBehavior();
        if (!empty($anomalous)) {
            $threats[] = [
                'type' => 'anomalous_behavior',
                'data' => $anomalous,
                'severity' => 'medium',
            ];
        }
        
        return $threats;
    }
    
    private function detectBruteForceAttacks(): array
    {
        // Find IPs with excessive failed authentication attempts
        $suspiciousIPs = DB::table('oauth_metrics')
            ->select('ip_address')
            ->selectRaw('COUNT(*) as failed_attempts')
            ->where('created_at', '>=', now()->subMinutes(10))
            ->where('status_code', 401)
            ->whereNotNull('ip_address')
            ->groupBy('ip_address')
            ->having('failed_attempts', '>=', 20)
            ->get();
        
        return $suspiciousIPs->toArray();
    }
    
    private function detectTokenAbuse(): array
    {
        // Find tokens being used from multiple IP addresses
        $abusedTokens = DB::table('oauth_metrics')
            ->select('client_id')
            ->selectRaw('COUNT(DISTINCT ip_address) as unique_ips')
            ->selectRaw('COUNT(*) as total_requests')
            ->where('created_at', '>=', now()->subHour())
            ->where('status_code', 200)
            ->whereNotNull('client_id')
            ->groupBy('client_id')
            ->having('unique_ips', '>=', 10)
            ->having('total_requests', '>=', 100)
            ->get();
        
        return $abusedTokens->toArray();
    }
    
    private function respondToThreat(array $threat): void
    {
        match ($threat['type']) {
            'brute_force' => $this->respondToBruteForce($threat['data']),
            'token_abuse' => $this->respondToTokenAbuse($threat['data']),
            'anomalous_behavior' => $this->respondToAnomalousBehavior($threat['data']),
        };
        
        // Log response action
        OAuthAuditService::logThreatResponse($threat);
    }
}
```

## Security Best Practices Checklist

### Development Security
- [ ] Use HTTPS everywhere
- [ ] Implement PKCE for all OAuth flows
- [ ] Use short-lived access tokens
- [ ] Implement refresh token rotation
- [ ] Validate all inputs
- [ ] Use parameterized queries
- [ ] Implement proper error handling
- [ ] Add comprehensive logging

### Infrastructure Security
- [ ] Regular security updates
- [ ] Network segmentation
- [ ] Firewall configuration
- [ ] DDoS protection
- [ ] SSL/TLS configuration
- [ ] Regular backups
- [ ] Monitoring and alerting
- [ ] Incident response plan

### Operational Security
- [ ] Access control policies
- [ ] Multi-factor authentication
- [ ] Regular security audits
- [ ] Vulnerability scanning
- [ ] Penetration testing
- [ ] Security training
- [ ] Key rotation procedures
- [ ] Disaster recovery plan

## Compliance Considerations

### GDPR Compliance
- Data minimization in token storage
- Right to deletion implementation
- Data processing logging
- Privacy by design principles

### SOC 2 Compliance
- Access controls and monitoring
- System availability measures
- Confidentiality protections
- Processing integrity controls

### OAuth 2.1 Security Best Practices
- PKCE for all clients
- No implicit grant type
- Short access token lifetimes
- Refresh token rotation

## Security Testing

### Automated Security Tests

```php
class SecurityTest extends TestCase
{
    /** @test */
    public function it_prevents_authorization_code_replay_attacks()
    {
        // Create authorization code
        $code = $this->createAuthorizationCode();
        
        // Use code first time - should succeed
        $response1 = $this->exchangeCodeForToken($code);
        $this->assertEquals(200, $response1->status());
        
        // Try to use same code again - should fail
        $response2 = $this->exchangeCodeForToken($code);
        $this->assertEquals(400, $response2->status());
        $this->assertEquals('invalid_grant', $response2->json('error'));
    }
    
    /** @test */
    public function it_validates_pkce_code_verifier()
    {
        $codeVerifier = 'invalid-verifier';
        $codeChallenge = 'different-challenge';
        
        $response = $this->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => 'test-client',
            'code' => $this->createAuthorizationCode($codeChallenge),
            'code_verifier' => $codeVerifier,
        ]);
        
        $response->assertStatus(400);
        $response->assertJson(['error' => 'invalid_grant']);
    }
    
    /** @test */
    public function it_enforces_rate_limits()
    {
        $client = OAuthClient::factory()->create();
        
        // Make requests up to the limit
        for ($i = 0; $i < 60; $i++) {
            $response = $this->post('/oauth/token', [
                'grant_type' => 'client_credentials',
                'client_id' => $client->client_id,
                'client_secret' => $client->client_secret,
            ]);
            
            if ($i < 59) {
                $this->assertEquals(200, $response->status());
            }
        }
        
        // Next request should be rate limited
        $response = $this->post('/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->client_id,
            'client_secret' => $client->client_secret,
        ]);
        
        $response->assertStatus(429);
    }
}
```

## Next Steps

After implementing security measures:

1. [🚀 Review deployment guide](deployment.md)
2. [📊 Setup monitoring](monitoring.md)
3. [🔧 Test OAuth2 flows](oauth2-setup.md)
4. [❓ Check troubleshooting guide](troubleshooting.md)