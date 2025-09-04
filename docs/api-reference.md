# 🔌 API Reference

Complete API reference for the Laravel OAuth2/OIDC Identity Provider endpoints.

## Base URL

```
https://oauth-provider.com
```

All API endpoints use HTTPS and follow OAuth 2.1 and OpenID Connect Core 1.0 specifications.

## Authentication

### Client Authentication Methods

The OAuth provider supports multiple client authentication methods:

| Method | Description | Usage |
|--------|-------------|-------|
| `client_secret_basic` | HTTP Basic Authentication | Recommended for server-side applications |
| `client_secret_post` | Client credentials in POST body | Alternative for server-side applications |
| `client_secret_jwt` | JWT with shared secret | High security applications |
| `private_key_jwt` | JWT with private key | Maximum security applications |
| `none` | No authentication | Public clients only (with PKCE) |

## OAuth2 Endpoints

### Authorization Endpoint

Initiates the OAuth 2.0 authorization flow.

```http
GET /oauth/authorize
```

#### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `response_type` | string | Yes | Must be `code` |
| `client_id` | string | Yes | Client identifier |
| `redirect_uri` | string | Yes | Callback URL |
| `scope` | string | No | Space-delimited scopes |
| `state` | string | Recommended | CSRF protection token |
| `code_challenge` | string | Required* | PKCE code challenge |
| `code_challenge_method` | string | Required* | Must be `S256` |
| `nonce` | string | OIDC | Unique request identifier |
| `prompt` | string | No | `none`, `login`, `consent`, `select_account` |
| `max_age` | integer | No | Maximum authentication age in seconds |
| `ui_locales` | string | No | Preferred languages |
| `acr_values` | string | No | Authentication context class references |

*Required for public clients and recommended for confidential clients.

#### Example Request

```http
GET /oauth/authorize?response_type=code&client_id=web-app-client&redirect_uri=https%3A%2F%2Fmyapp.com%2Fcallback&scope=openid%20profile%20email&state=xyz123&code_challenge=E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM&code_challenge_method=S256&nonce=abc123
```

#### Responses

**Success (302 Found)**
```http
HTTP/1.1 302 Found
Location: https://myapp.com/callback?code=def50200f2946597e9bdf575551f153289184aef5f5e0c51c450f393bec31fa581bb425b524d4b9d204ba1736dd1bfba471281db&state=xyz123
```

**Error (302 Found)**
```http
HTTP/1.1 302 Found
Location: https://myapp.com/callback?error=invalid_request&error_description=Invalid%20redirect%20URI&state=xyz123
```

### Token Endpoint

Exchanges authorization codes for access tokens.

```http
POST /oauth/token
Content-Type: application/x-www-form-urlencoded
```

#### Authorization Code Grant

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `grant_type` | string | Yes | Must be `authorization_code` |
| `code` | string | Yes | Authorization code |
| `redirect_uri` | string | Yes | Must match authorization request |
| `client_id` | string | Yes | Client identifier |
| `client_secret` | string | Conditional | Required for confidential clients |
| `code_verifier` | string | Conditional | PKCE code verifier |

#### Client Credentials Grant

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `grant_type` | string | Yes | Must be `client_credentials` |
| `client_id` | string | Yes | Client identifier |
| `client_secret` | string | Yes | Client secret |
| `scope` | string | No | Requested scopes |

#### Refresh Token Grant

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `grant_type` | string | Yes | Must be `refresh_token` |
| `refresh_token` | string | Yes | Valid refresh token |
| `client_id` | string | Yes | Client identifier |
| `client_secret` | string | Conditional | Required for confidential clients |
| `scope` | string | No | Requested scopes (must be subset) |

#### Example Requests

**Authorization Code Grant**
```http
POST /oauth/token
Content-Type: application/x-www-form-urlencoded
Authorization: Basic d2ViLWFwcC1jbGllbnQ6Y2xpZW50LXNlY3JldA==

grant_type=authorization_code&code=def50200f2946597e9bdf575551f153289184aef5f5e0c51c450f393bec31fa581bb425b524d4b9d204ba1736dd1bfba471281db&redirect_uri=https%3A%2F%2Fmyapp.com%2Fcallback&code_verifier=dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk
```

**Client Credentials Grant**
```http
POST /oauth/token
Content-Type: application/x-www-form-urlencoded

grant_type=client_credentials&client_id=api-client&client_secret=client-secret&scope=api:read api:write
```

**Refresh Token Grant**
```http
POST /oauth/token
Content-Type: application/x-www-form-urlencoded

grant_type=refresh_token&refresh_token=def50200e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855&client_id=web-app-client&client_secret=client-secret
```

#### Success Response

```json
{
  "token_type": "Bearer",
  "expires_in": 3600,
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
  "refresh_token": "def50200e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
  "scope": "openid profile email",
  "id_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9..."
}
```

#### Error Response

```json
{
  "error": "invalid_grant",
  "error_description": "The provided authorization grant is invalid, expired, or revoked"
}
```

### Token Introspection Endpoint

Validates and provides information about access tokens.

```http
POST /oauth/introspect
Content-Type: application/x-www-form-urlencoded
Authorization: Basic base64(client_id:client_secret)
```

#### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `token` | string | Yes | Token to introspect |
| `token_type_hint` | string | No | `access_token` or `refresh_token` |

#### Example Request

```http
POST /oauth/introspect
Content-Type: application/x-www-form-urlencoded
Authorization: Basic YXBpLWNsaWVudDpjbGllbnQtc2VjcmV0

token=eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...&token_type_hint=access_token
```

#### Active Token Response

```json
{
  "active": true,
  "scope": "openid profile email",
  "client_id": "web-app-client",
  "username": "user@example.com",
  "exp": 1635724800,
  "iat": 1635721200,
  "nbf": 1635721200,
  "sub": "user-123",
  "aud": ["web-app-client"],
  "iss": "https://oauth-provider.com",
  "token_type": "Bearer",
  "jti": "token-unique-id"
}
```

#### Inactive Token Response

```json
{
  "active": false
}
```

### Token Revocation Endpoint

Revokes access or refresh tokens.

```http
POST /oauth/revoke
Content-Type: application/x-www-form-urlencoded
Authorization: Basic base64(client_id:client_secret)
```

#### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `token` | string | Yes | Token to revoke |
| `token_type_hint` | string | No | `access_token` or `refresh_token` |

#### Example Request

```http
POST /oauth/revoke
Content-Type: application/x-www-form-urlencoded
Authorization: Basic d2ViLWFwcC1jbGllbnQ6Y2xpZW50LXNlY3JldA==

token=def50200e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855&token_type_hint=refresh_token
```

#### Success Response

```http
HTTP/1.1 200 OK
```

## OpenID Connect Endpoints

### UserInfo Endpoint

Returns claims about the authenticated user.

```http
GET /oauth/userinfo
Authorization: Bearer access_token
```

#### Example Request

```http
GET /oauth/userinfo
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...
```

#### Success Response

```json
{
  "sub": "user-123",
  "name": "John Doe",
  "given_name": "John",
  "family_name": "Doe",
  "preferred_username": "johndoe",
  "email": "john.doe@example.com",
  "email_verified": true,
  "picture": "https://example.com/avatar.jpg",
  "locale": "en-US",
  "zoneinfo": "America/New_York",
  "updated_at": 1635721200
}
```

#### Error Response

```json
{
  "error": "invalid_token",
  "error_description": "The access token provided is expired, revoked, malformed, or invalid"
}
```

### Discovery Endpoint

Returns OpenID Connect configuration.

```http
GET /.well-known/openid-configuration
```

#### Example Response

```json
{
  "issuer": "https://oauth-provider.com",
  "authorization_endpoint": "https://oauth-provider.com/oauth/authorize",
  "token_endpoint": "https://oauth-provider.com/oauth/token",
  "userinfo_endpoint": "https://oauth-provider.com/oauth/userinfo",
  "jwks_uri": "https://oauth-provider.com/.well-known/jwks.json",
  "introspection_endpoint": "https://oauth-provider.com/oauth/introspect",
  "revocation_endpoint": "https://oauth-provider.com/oauth/revoke",
  "response_types_supported": ["code"],
  "grant_types_supported": [
    "authorization_code",
    "refresh_token",
    "client_credentials"
  ],
  "subject_types_supported": ["public"],
  "id_token_signing_alg_values_supported": ["RS256"],
  "scopes_supported": [
    "openid",
    "profile",
    "email",
    "phone",
    "address"
  ],
  "token_endpoint_auth_methods_supported": [
    "client_secret_basic",
    "client_secret_post",
    "client_secret_jwt",
    "private_key_jwt"
  ],
  "claims_supported": [
    "sub",
    "name",
    "given_name",
    "family_name",
    "preferred_username",
    "email",
    "email_verified",
    "picture",
    "locale",
    "zoneinfo",
    "updated_at"
  ],
  "code_challenge_methods_supported": ["S256"],
  "request_parameter_supported": false,
  "request_uri_parameter_supported": false,
  "require_request_uri_registration": false,
  "op_policy_uri": "https://oauth-provider.com/privacy",
  "op_tos_uri": "https://oauth-provider.com/terms"
}
```

### JWKS Endpoint

Returns JSON Web Key Set for token verification.

```http
GET /.well-known/jwks.json
```

#### Example Response

```json
{
  "keys": [
    {
      "kty": "RSA",
      "use": "sig",
      "kid": "2023-01-15",
      "alg": "RS256",
      "n": "0vx7agoebGcQSuuPiLJXZptN9nndrQmbXEps2aiAFbWhM78LhWx4cbbfAAtVT86zwu1RK7aPFFxuhDR1L6tSoc_BJECPebWKRXjBZCiFV4n3oknjhMstn64tZ_2W-5JsGY4Hc5n9yBXArwl93lqt7_RN5w6Cf0h4QyQ5v-65YGjQR0_FDW2QvzqY368QQMicAtaSqzs8KJZgnYb9c7d0zgdAZHzu6qMQvRL5hajrn1n91CbOpbISD08qNLyrdkt-bFTWhAI4vMQFh6WeZu0fM4lFd2NcRwr3XPksINHaQ-G_xBniIqbw0Ls1jF44-csFCur-kEgU8awapJzKnqDKgw",
      "e": "AQAB"
    }
  ]
}
```

## Client Management API

### List Clients

```http
GET /api/oauth/clients
Authorization: Bearer admin_access_token
```

#### Query Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `page` | integer | Page number (default: 1) |
| `per_page` | integer | Items per page (default: 20) |
| `search` | string | Search by name or client ID |
| `type` | string | Filter by client type (`confidential`, `public`) |

#### Example Response

```json
{
  "data": [
    {
      "client_id": "web-app-client",
      "name": "Web Application",
      "type": "confidential",
      "redirect_uris": [
        "https://myapp.com/callback"
      ],
      "scopes": ["openid", "profile", "email"],
      "grant_types": ["authorization_code", "refresh_token"],
      "created_at": "2024-01-15T10:00:00Z",
      "updated_at": "2024-01-15T10:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 1,
    "last_page": 1
  }
}
```

### Create Client

```http
POST /api/oauth/clients
Authorization: Bearer admin_access_token
Content-Type: application/json
```

#### Request Body

```json
{
  "name": "My Application",
  "type": "confidential",
  "redirect_uris": [
    "https://myapp.com/callback"
  ],
  "scopes": ["openid", "profile", "email"],
  "grant_types": ["authorization_code", "refresh_token"]
}
```

#### Success Response

```json
{
  "client_id": "generated-client-id",
  "client_secret": "generated-client-secret",
  "name": "My Application",
  "type": "confidential",
  "redirect_uris": [
    "https://myapp.com/callback"
  ],
  "scopes": ["openid", "profile", "email"],
  "grant_types": ["authorization_code", "refresh_token"],
  "created_at": "2024-01-15T10:00:00Z"
}
```

### Update Client

```http
PUT /api/oauth/clients/{client_id}
Authorization: Bearer admin_access_token
Content-Type: application/json
```

#### Request Body

```json
{
  "name": "Updated Application Name",
  "redirect_uris": [
    "https://myapp.com/callback",
    "https://myapp.com/auth/callback"
  ],
  "scopes": ["openid", "profile", "email", "phone"]
}
```

### Delete Client

```http
DELETE /api/oauth/clients/{client_id}
Authorization: Bearer admin_access_token
```

#### Success Response

```http
HTTP/1.1 204 No Content
```

## Metrics API

### Get Metrics Overview

```http
GET /api/oauth/metrics/overview
Authorization: Bearer admin_access_token
```

#### Query Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `range` | string | Time range (`1h`, `24h`, `7d`, `30d`) |
| `client_id` | string | Filter by client ID |

#### Example Response

```json
{
  "summary": {
    "total_requests": 12543,
    "avg_response_time": 94,
    "error_rate": 2.1,
    "active_clients": 23,
    "active_users": 1847
  },
  "endpoints": {
    "token": {
      "requests": 5643,
      "avg_response_time": 156,
      "error_rate": 1.2
    },
    "authorize": {
      "requests": 5643,
      "avg_response_time": 45,
      "error_rate": 0.8
    },
    "userinfo": {
      "requests": 1257,
      "avg_response_time": 67,
      "error_rate": 0.3
    }
  },
  "trends": {
    "hourly_requests": [234, 189, 267, 301],
    "response_times": {
      "avg": [89, 94, 87, 91],
      "p95": [145, 152, 139, 148],
      "p99": [289, 301, 267, 294]
    }
  }
}
```

### Get Client Metrics

```http
GET /api/oauth/metrics/clients/{client_id}
Authorization: Bearer admin_access_token
```

#### Example Response

```json
{
  "client_id": "web-app-client",
  "client_name": "Web Application",
  "metrics": {
    "total_requests": 5643,
    "avg_response_time": 87,
    "error_rate": 1.2,
    "active_tokens": 234,
    "unique_users": 456
  },
  "trends": {
    "daily_requests": [543, 621, 589, 654, 701, 623, 567],
    "error_trends": [0.8, 1.1, 1.5, 1.2, 0.9, 1.3, 1.2]
  },
  "top_errors": [
    {
      "error": "invalid_grant",
      "count": 12,
      "percentage": 0.21
    },
    {
      "error": "invalid_client",
      "count": 8,
      "percentage": 0.14
    }
  ]
}
```

## Health Check API

### System Health

```http
GET /api/health
```

#### Example Response

```json
{
  "status": "healthy",
  "timestamp": "2024-01-15T10:30:00Z",
  "uptime": "15 days, 4 hours, 32 minutes",
  "checks": {
    "database": {
      "status": "healthy",
      "response_time": 12
    },
    "redis": {
      "status": "healthy",
      "response_time": 3
    },
    "oauth": {
      "status": "healthy",
      "metrics": {
        "requests_5min": 245,
        "avg_response_time": 89,
        "error_rate": 1.2
      }
    }
  }
}
```

### OAuth Health

```http
GET /api/oauth/health
Authorization: Bearer admin_access_token
```

#### Example Response

```json
{
  "status": "healthy",
  "oauth_server": {
    "status": "healthy",
    "active_tokens": 1847,
    "token_generation_rate": 12.3,
    "avg_token_lifetime": 3456
  },
  "key_status": {
    "private_key": "healthy",
    "public_key": "healthy",
    "key_age_days": 45,
    "rotation_due": false
  },
  "endpoints": {
    "authorize": "healthy",
    "token": "healthy",
    "userinfo": "healthy",
    "introspect": "healthy"
  }
}
```

## Error Responses

### Standard Error Format

All error responses follow the OAuth 2.0 error format:

```json
{
  "error": "error_code",
  "error_description": "Human-readable error description",
  "error_uri": "https://oauth-provider.com/docs/errors#error_code"
}
```

### Common Error Codes

#### OAuth 2.0 Errors

| Error Code | HTTP Status | Description |
|------------|-------------|-------------|
| `invalid_request` | 400 | Missing or invalid parameters |
| `invalid_client` | 401 | Client authentication failed |
| `invalid_grant` | 400 | Invalid or expired authorization grant |
| `unauthorized_client` | 400 | Client not authorized for grant type |
| `unsupported_grant_type` | 400 | Grant type not supported |
| `invalid_scope` | 400 | Invalid or unknown scope |
| `access_denied` | 400 | User denied authorization |
| `temporarily_unavailable` | 503 | Server temporarily unavailable |

#### OpenID Connect Errors

| Error Code | HTTP Status | Description |
|------------|-------------|-------------|
| `invalid_token` | 401 | Invalid, expired, or revoked token |
| `insufficient_scope` | 403 | Token lacks required scope |
| `interaction_required` | 400 | User interaction required |
| `login_required` | 400 | User authentication required |
| `consent_required` | 400 | User consent required |

#### Rate Limiting Errors

| Error Code | HTTP Status | Description |
|------------|-------------|-------------|
| `rate_limit_exceeded` | 429 | Too many requests |

### Error Response Headers

Rate-limited responses include additional headers:

```http
HTTP/1.1 429 Too Many Requests
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1635721260
Retry-After: 60
```

## Rate Limits

### Default Rate Limits

| Endpoint | Limit | Window |
|----------|-------|---------|
| `/oauth/authorize` | 120 requests | per minute |
| `/oauth/token` | 60 requests | per minute |
| `/oauth/userinfo` | 300 requests | per minute |
| `/oauth/introspect` | 180 requests | per minute |
| `/oauth/revoke` | 60 requests | per minute |
| `/.well-known/*` | 1000 requests | per minute |

### Rate Limit Headers

All responses include rate limit information:

```http
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 45
X-RateLimit-Reset: 1635721260
```

## Webhooks

### Webhook Events

The OAuth provider can send webhooks for various events:

| Event Type | Description |
|------------|-------------|
| `token.issued` | Access token issued |
| `token.refreshed` | Access token refreshed |
| `token.revoked` | Token revoked |
| `client.created` | New client created |
| `client.updated` | Client updated |
| `client.deleted` | Client deleted |
| `user.authenticated` | User successfully authenticated |
| `user.authentication_failed` | Authentication failed |
| `security.suspicious_activity` | Suspicious activity detected |

### Webhook Payload

```json
{
  "id": "webhook-event-123",
  "type": "token.issued",
  "timestamp": "2024-01-15T10:30:00Z",
  "data": {
    "client_id": "web-app-client",
    "user_id": 123,
    "token_id": "token-abc123",
    "scopes": ["openid", "profile", "email"],
    "expires_at": "2024-01-15T11:30:00Z"
  },
  "metadata": {
    "ip_address": "192.168.1.100",
    "user_agent": "Mozilla/5.0...",
    "request_id": "req-456789"
  }
}
```

## SDK Examples

### JavaScript/Node.js

```javascript
import { OAuthProvider } from '@your-org/oauth-client';

const oauth = new OAuthProvider({
  clientId: 'your-client-id',
  clientSecret: 'your-client-secret',
  baseUrl: 'https://oauth-provider.com',
  redirectUri: 'https://yourapp.com/callback'
});

// Authorization Code Flow with PKCE
const authUrl = await oauth.getAuthorizationUrl({
  scopes: ['openid', 'profile', 'email'],
  state: 'random-state-value'
});

// Exchange code for tokens
const tokens = await oauth.exchangeCodeForTokens(authorizationCode, codeVerifier);

// Refresh tokens
const newTokens = await oauth.refreshTokens(refreshToken);

// Get user info
const userInfo = await oauth.getUserInfo(accessToken);
```

### Python

```python
from oauth_provider_client import OAuthClient

client = OAuthClient(
    client_id='your-client-id',
    client_secret='your-client-secret',
    base_url='https://oauth-provider.com',
    redirect_uri='https://yourapp.com/callback'
)

# Authorization Code Flow
auth_url, state, code_verifier = client.get_authorization_url(
    scopes=['openid', 'profile', 'email']
)

# Exchange code for tokens
tokens = client.exchange_code_for_tokens(
    authorization_code=code,
    code_verifier=code_verifier
)

# Get user info
user_info = client.get_user_info(tokens['access_token'])
```

### PHP

```php
use YourOrg\OAuthClient\OAuthProvider;

$oauth = new OAuthProvider([
    'clientId' => 'your-client-id',
    'clientSecret' => 'your-client-secret',
    'baseUrl' => 'https://oauth-provider.com',
    'redirectUri' => 'https://yourapp.com/callback'
]);

// Get authorization URL
$authUrl = $oauth->getAuthorizationUrl([
    'scopes' => ['openid', 'profile', 'email'],
    'state' => 'random-state-value'
]);

// Exchange code for tokens
$tokens = $oauth->exchangeCodeForTokens($authorizationCode, $codeVerifier);

// Get user info
$userInfo = $oauth->getUserInfo($tokens['access_token']);
```

## Testing

### Testing Endpoints

Use the following curl commands to test endpoints:

```bash
# Test discovery endpoint
curl https://oauth-provider.com/.well-known/openid-configuration

# Test JWKS endpoint
curl https://oauth-provider.com/.well-known/jwks.json

# Test token introspection
curl -X POST https://oauth-provider.com/oauth/introspect \
  -H "Authorization: Basic $(echo -n 'client_id:client_secret' | base64)" \
  -d "token=your-access-token"

# Test user info endpoint
curl -H "Authorization: Bearer your-access-token" \
     https://oauth-provider.com/oauth/userinfo
```

### Postman Collection

Import the [Postman collection](../postman/oauth2-collection.json) for easy testing:

1. Download the collection file
2. Import into Postman
3. Set environment variables
4. Run the requests in order

## Support

For API support:
- 📖 [Documentation](https://docs.oauth-provider.com)
- 💬 [GitHub Discussions](https://github.com/your-org/oauth-provider/discussions)
- 🐛 [Report Issues](https://github.com/your-org/oauth-provider/issues)
- 📧 [Email Support](mailto:support@oauth-provider.com)