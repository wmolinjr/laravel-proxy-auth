# 🔧 OAuth2 Setup Guide

This guide covers how to set up and configure OAuth2 clients and flows in your Identity Provider.

## OAuth2 Basics

### Supported Grant Types

1. **Authorization Code Grant** (Recommended)
   - Most secure flow for confidential clients
   - Supports PKCE for enhanced security
   - Suitable for server-side applications

2. **Client Credentials Grant**
   - For machine-to-machine communication
   - No user interaction required
   - Suitable for APIs and services

3. **Refresh Token Grant**
   - Extends access token lifetime
   - Maintains user sessions securely

### Supported Client Types

- **Confidential Clients**: Can securely store client secrets
- **Public Clients**: Cannot securely store secrets (mobile apps, SPAs)

## Creating OAuth2 Clients

### Using Admin Interface

1. **Access Admin Dashboard**
   ```
   https://your-oauth-provider.com/admin
   ```

2. **Navigate to OAuth Clients**
   - Go to "OAuth Management" → "Clients"
   - Click "Create New Client"

3. **Configure Client Settings**
   - **Name**: Human-readable client name
   - **Type**: Confidential or Public
   - **Redirect URIs**: Allowed callback URLs
   - **Scopes**: Permitted access scopes
   - **Grant Types**: Enabled grant types

### Using Artisan Commands

```bash
# Create a new OAuth client
php artisan oauth:client

# Create a client with specific settings
php artisan oauth:client \
    --name="My Application" \
    --redirect="https://myapp.com/callback" \
    --type=confidential \
    --scopes="openid,profile,email"

# List all clients
php artisan oauth:client --list

# Show client details
php artisan oauth:client --show=client-id-here
```

### Programmatic Client Creation

```php
use App\Models\OAuth\OAuthClient;

$client = OAuthClient::create([
    'name' => 'My Application',
    'client_id' => 'my-app-client',
    'client_secret' => hash('sha256', Str::random(40)),
    'redirect_uris' => [
        'https://myapp.com/callback',
        'https://myapp.com/auth/callback'
    ],
    'is_confidential' => true,
    'scopes' => ['openid', 'profile', 'email'],
    'grant_types' => [
        'authorization_code',
        'refresh_token'
    ]
]);
```

## OAuth2 Flows

### Authorization Code Flow (with PKCE)

#### Step 1: Authorization Request
```javascript
// Generate PKCE parameters
const codeVerifier = generateCodeVerifier();
const codeChallenge = await generateCodeChallenge(codeVerifier);
const state = generateRandomString();

// Build authorization URL
const authUrl = new URL('https://oauth-provider.com/oauth/authorize');
authUrl.searchParams.set('response_type', 'code');
authUrl.searchParams.set('client_id', 'your-client-id');
authUrl.searchParams.set('redirect_uri', 'https://yourapp.com/callback');
authUrl.searchParams.set('scope', 'openid profile email');
authUrl.searchParams.set('state', state);
authUrl.searchParams.set('code_challenge', codeChallenge);
authUrl.searchParams.set('code_challenge_method', 'S256');

// Redirect user to authorization URL
window.location.href = authUrl.toString();
```

#### Step 2: Handle Callback
```javascript
// Parse callback parameters
const urlParams = new URLSearchParams(window.location.search);
const code = urlParams.get('code');
const state = urlParams.get('state');

// Verify state parameter
if (state !== storedState) {
    throw new Error('Invalid state parameter');
}
```

#### Step 3: Exchange Code for Tokens
```javascript
const tokenResponse = await fetch('https://oauth-provider.com/oauth/token', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'Accept': 'application/json',
    },
    body: new URLSearchParams({
        grant_type: 'authorization_code',
        client_id: 'your-client-id',
        client_secret: 'your-client-secret', // Only for confidential clients
        code: code,
        redirect_uri: 'https://yourapp.com/callback',
        code_verifier: codeVerifier,
    }),
});

const tokens = await tokenResponse.json();
// tokens.access_token, tokens.refresh_token, tokens.id_token
```

### Client Credentials Flow

```javascript
const tokenResponse = await fetch('https://oauth-provider.com/oauth/token', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'Authorization': `Basic ${btoa(`${clientId}:${clientSecret}`)}`,
    },
    body: new URLSearchParams({
        grant_type: 'client_credentials',
        scope: 'api:read api:write',
    }),
});

const tokens = await tokenResponse.json();
```

### Refresh Token Flow

```javascript
const refreshResponse = await fetch('https://oauth-provider.com/oauth/token', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'Accept': 'application/json',
    },
    body: new URLSearchParams({
        grant_type: 'refresh_token',
        refresh_token: storedRefreshToken,
        client_id: 'your-client-id',
        client_secret: 'your-client-secret',
    }),
});

const newTokens = await refreshResponse.json();
```

## OpenID Connect (OIDC)

### Discovery Endpoint

```javascript
const discovery = await fetch('https://oauth-provider.com/.well-known/openid-configuration')
    .then(r => r.json());

console.log(discovery);
// Contains all endpoint URLs and supported features
```

### ID Token Validation

```javascript
import jwt from 'jsonwebtoken';
import jwksClient from 'jwks-rsa';

const client = jwksClient({
    jwksUri: 'https://oauth-provider.com/.well-known/jwks.json'
});

function getKey(header, callback) {
    client.getSigningKey(header.kid, (err, key) => {
        const signingKey = key.publicKey || key.rsaPublicKey;
        callback(null, signingKey);
    });
}

// Verify ID token
jwt.verify(idToken, getKey, {
    audience: 'your-client-id',
    issuer: 'https://oauth-provider.com',
    algorithms: ['RS256']
}, (err, decoded) => {
    if (err) {
        console.error('Token verification failed:', err);
        return;
    }
    
    console.log('User info:', decoded);
});
```

### UserInfo Endpoint

```javascript
const userInfo = await fetch('https://oauth-provider.com/oauth/userinfo', {
    headers: {
        'Authorization': `Bearer ${accessToken}`,
    },
}).then(r => r.json());

console.log(userInfo);
// Contains user claims based on requested scopes
```

## Client Configuration Examples

### Web Application (Confidential Client)

```json
{
    "client_id": "web-app-client",
    "client_secret": "secret-key-here",
    "client_type": "confidential",
    "redirect_uris": [
        "https://mywebapp.com/auth/callback"
    ],
    "grant_types": [
        "authorization_code",
        "refresh_token"
    ],
    "scopes": [
        "openid",
        "profile",
        "email"
    ],
    "require_pkce": true
}
```

### Single Page Application (Public Client)

```json
{
    "client_id": "spa-client",
    "client_secret": null,
    "client_type": "public",
    "redirect_uris": [
        "https://myspa.com/callback",
        "http://localhost:3000/callback"
    ],
    "grant_types": [
        "authorization_code"
    ],
    "scopes": [
        "openid",
        "profile",
        "email"
    ],
    "require_pkce": true,
    "token_endpoint_auth_method": "none"
}
```

### Mobile Application

```json
{
    "client_id": "mobile-app-client",
    "client_secret": null,
    "client_type": "public",
    "redirect_uris": [
        "com.myapp.oauth://callback",
        "https://myapp.com/mobile-callback"
    ],
    "grant_types": [
        "authorization_code",
        "refresh_token"
    ],
    "scopes": [
        "openid",
        "profile",
        "email",
        "offline_access"
    ],
    "require_pkce": true
}
```

### API Service (Machine-to-Machine)

```json
{
    "client_id": "api-service-client",
    "client_secret": "api-secret-key",
    "client_type": "confidential",
    "redirect_uris": [],
    "grant_types": [
        "client_credentials"
    ],
    "scopes": [
        "api:read",
        "api:write",
        "admin:users"
    ],
    "token_endpoint_auth_method": "client_secret_basic"
}
```

## Scope Configuration

### Default Scopes

```php
// config/oauth.php
'scopes' => [
    'openid' => [
        'name' => 'OpenID Connect',
        'description' => 'Provides access to the user\'s OpenID Connect identity',
        'claims' => ['sub', 'iss', 'aud', 'exp', 'iat'],
        'required_for_oidc' => true,
    ],
    
    'profile' => [
        'name' => 'Profile Information',
        'description' => 'Access to basic profile information',
        'claims' => [
            'name',
            'family_name', 
            'given_name',
            'middle_name',
            'nickname',
            'preferred_username',
            'profile',
            'picture',
            'website',
            'gender',
            'birthdate',
            'zoneinfo',
            'locale',
            'updated_at'
        ],
    ],
    
    'email' => [
        'name' => 'Email Address',
        'description' => 'Access to the user\'s email address',
        'claims' => ['email', 'email_verified'],
    ],
    
    'phone' => [
        'name' => 'Phone Number',
        'description' => 'Access to the user\'s phone number',
        'claims' => ['phone_number', 'phone_number_verified'],
    ],
    
    'address' => [
        'name' => 'Address Information',
        'description' => 'Access to the user\'s address',
        'claims' => ['address'],
    ],
];
```

### Custom Scopes

```php
// Add custom scopes
'scopes' => [
    // ... default scopes
    
    'admin' => [
        'name' => 'Admin Access',
        'description' => 'Full administrative access',
        'restricted' => true,
        'permissions' => [
            'users.read',
            'users.write',
            'clients.read',
            'clients.write',
            'metrics.read',
        ],
    ],
    
    'api:read' => [
        'name' => 'API Read Access',
        'description' => 'Read-only access to API resources',
        'resource_server' => 'api',
        'permissions' => ['read'],
    ],
    
    'api:write' => [
        'name' => 'API Write Access', 
        'description' => 'Write access to API resources',
        'resource_server' => 'api',
        'permissions' => ['read', 'write'],
    ],
];
```

## Token Introspection

### Introspect Access Token

```bash
curl -X POST https://oauth-provider.com/oauth/introspect \
  -H "Authorization: Basic $(echo -n 'client_id:client_secret' | base64)" \
  -d "token=your-access-token"
```

Response:
```json
{
    "active": true,
    "scope": "openid profile email",
    "client_id": "your-client-id",
    "username": "user@example.com",
    "exp": 1635724800,
    "iat": 1635721200,
    "sub": "user-123",
    "aud": ["your-client-id"],
    "iss": "https://oauth-provider.com",
    "token_type": "Bearer"
}
```

## Testing OAuth2 Flow

### Using cURL

```bash
# Step 1: Get authorization code (manual browser step)
# Visit: https://oauth-provider.com/oauth/authorize?response_type=code&client_id=test-client&redirect_uri=https://example.com/callback&scope=openid+profile&state=random-state

# Step 2: Exchange code for tokens
curl -X POST https://oauth-provider.com/oauth/token \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "grant_type=authorization_code" \
  -d "client_id=test-client" \
  -d "client_secret=test-secret" \
  -d "code=received-auth-code" \
  -d "redirect_uri=https://example.com/callback"

# Step 3: Use access token
curl -H "Authorization: Bearer your-access-token" \
     https://oauth-provider.com/oauth/userinfo
```

### Using Postman

1. **Import OAuth2 Collection**
   - Download the [Postman collection](../postman/oauth2-collection.json)
   - Import into Postman

2. **Configure Environment Variables**
   ```json
   {
     "oauth_base_url": "https://oauth-provider.com",
     "client_id": "your-client-id",
     "client_secret": "your-client-secret",
     "redirect_uri": "https://yourapp.com/callback"
   }
   ```

3. **Run Authorization Code Flow**
   - Execute requests in order
   - Copy authorization code from browser
   - Use tokens for subsequent requests

## Common Integration Patterns

### Next.js Application

```javascript
// pages/api/auth/[...nextauth].js
import NextAuth from 'next-auth'

export default NextAuth({
    providers: [
        {
            id: "oauth-provider",
            name: "OAuth Provider",
            type: "oauth",
            authorization: {
                url: "https://oauth-provider.com/oauth/authorize",
                params: {
                    scope: "openid profile email",
                    response_type: "code",
                    code_challenge_method: "S256",
                }
            },
            token: "https://oauth-provider.com/oauth/token",
            userinfo: "https://oauth-provider.com/oauth/userinfo",
            clientId: process.env.OAUTH_CLIENT_ID,
            clientSecret: process.env.OAUTH_CLIENT_SECRET,
            issuer: "https://oauth-provider.com",
            checks: ["pkce", "state"],
            profile(profile) {
                return {
                    id: profile.sub,
                    name: profile.name,
                    email: profile.email,
                    image: profile.picture,
                }
            },
        }
    ],
    callbacks: {
        async jwt({ token, account }) {
            if (account) {
                token.accessToken = account.access_token
                token.refreshToken = account.refresh_token
            }
            return token
        },
        async session({ session, token }) {
            session.accessToken = token.accessToken
            return session
        },
    },
})
```

### React SPA with PKCE

```javascript
// oauth.js
class OAuth2Client {
    constructor(clientId, redirectUri, baseUrl) {
        this.clientId = clientId;
        this.redirectUri = redirectUri;
        this.baseUrl = baseUrl;
    }

    async login() {
        const codeVerifier = this.generateCodeVerifier();
        const codeChallenge = await this.generateCodeChallenge(codeVerifier);
        const state = this.generateRandomString();

        // Store for later use
        sessionStorage.setItem('code_verifier', codeVerifier);
        sessionStorage.setItem('state', state);

        const authUrl = new URL(`${this.baseUrl}/oauth/authorize`);
        authUrl.searchParams.set('response_type', 'code');
        authUrl.searchParams.set('client_id', this.clientId);
        authUrl.searchParams.set('redirect_uri', this.redirectUri);
        authUrl.searchParams.set('scope', 'openid profile email');
        authUrl.searchParams.set('state', state);
        authUrl.searchParams.set('code_challenge', codeChallenge);
        authUrl.searchParams.set('code_challenge_method', 'S256');

        window.location.href = authUrl.toString();
    }

    async handleCallback() {
        const urlParams = new URLSearchParams(window.location.search);
        const code = urlParams.get('code');
        const state = urlParams.get('state');
        
        const storedState = sessionStorage.getItem('state');
        const codeVerifier = sessionStorage.getItem('code_verifier');

        if (state !== storedState) {
            throw new Error('Invalid state parameter');
        }

        const tokenResponse = await fetch(`${this.baseUrl}/oauth/token`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                grant_type: 'authorization_code',
                client_id: this.clientId,
                code,
                redirect_uri: this.redirectUri,
                code_verifier: codeVerifier,
            }),
        });

        const tokens = await tokenResponse.json();
        
        // Store tokens securely
        localStorage.setItem('access_token', tokens.access_token);
        localStorage.setItem('refresh_token', tokens.refresh_token);
        
        // Clean up session storage
        sessionStorage.removeItem('code_verifier');
        sessionStorage.removeItem('state');

        return tokens;
    }

    generateCodeVerifier() {
        const array = new Uint8Array(32);
        crypto.getRandomValues(array);
        return base64URLEncode(array);
    }

    async generateCodeChallenge(codeVerifier) {
        const encoder = new TextEncoder();
        const data = encoder.encode(codeVerifier);
        const digest = await crypto.subtle.digest('SHA-256', data);
        return base64URLEncode(new Uint8Array(digest));
    }

    generateRandomString() {
        const array = new Uint8Array(32);
        crypto.getRandomValues(array);
        return base64URLEncode(array);
    }
}

function base64URLEncode(buffer) {
    return btoa(String.fromCharCode(...buffer))
        .replace(/\+/g, '-')
        .replace(/\//g, '_')
        .replace(/=/g, '');
}
```

## Security Best Practices

### Client Security

1. **Use PKCE**: Always enable PKCE for public clients
2. **Validate State**: Always validate the state parameter
3. **Secure Storage**: Store tokens securely (not in localStorage for sensitive apps)
4. **Token Rotation**: Implement refresh token rotation
5. **Scope Principle**: Request minimum required scopes

### Server Security

1. **HTTPS Only**: Always use HTTPS in production
2. **Short-lived Tokens**: Keep access tokens short-lived
3. **Rate Limiting**: Implement appropriate rate limits
4. **Audit Logging**: Log all OAuth events
5. **Regular Key Rotation**: Rotate signing keys regularly

## Troubleshooting

### Common Errors

#### Invalid Client
```json
{
    "error": "invalid_client",
    "error_description": "Client authentication failed"
}
```
**Solution**: Check client ID and secret

#### Invalid Grant
```json
{
    "error": "invalid_grant",
    "error_description": "The provided authorization grant is invalid"
}
```
**Solution**: Check authorization code hasn't expired or been used

#### Invalid Scope
```json
{
    "error": "invalid_scope",
    "error_description": "The requested scope is invalid"
}
```
**Solution**: Check client has permission for requested scopes

### Debug Mode

Enable debug logging:
```env
LOG_LEVEL=debug
OAUTH_DEBUG=true
```

Check logs:
```bash
tail -f storage/logs/oauth.log
```

## Next Steps

- [📊 Setup monitoring and metrics](monitoring.md)
- [🛡️ Review security configuration](security.md)
- [🔌 Explore API reference](api-reference.md)
- [🚀 Deploy to production](deployment.md)