# 🔐 Laravel OAuth2/OIDC Identity Provider

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-8892BF.svg)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/laravel-12.x-FF2D20.svg)](https://laravel.com/)
[![React](https://img.shields.io/badge/react-19-61DAFB.svg)](https://reactjs.org/)
[![TypeScript](https://img.shields.io/badge/typescript-5.x-3178C6.svg)](https://www.typescriptlang.org/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

A production-ready, enterprise-grade OAuth2/OpenID Connect Identity Provider built with Laravel 12, React 19, and modern web technologies. Features comprehensive metrics, audit logging, and advanced security controls.

## ✨ Features

### 🔒 **Authentication & Authorization**
- **OAuth2 Server** - Full RFC 6749 compliant implementation
- **OpenID Connect** - Complete OIDC Core 1.0 support
- **PKCE Support** - Enhanced security for public clients
- **Multiple Grant Types** - Authorization Code, Client Credentials, Refresh Token
- **Scope Management** - Fine-grained permission control
- **JWT Tokens** - Secure, stateless authentication

### 📊 **Monitoring & Analytics**
- **Real-time Metrics** - Performance monitoring for all OAuth endpoints
- **Comprehensive Audit Logs** - Track all critical authentication events
- **Health Monitoring** - Built-in health checks with configurable alerts
- **Performance Analytics** - Response times, error rates, and throughput metrics
- **Suspicious Activity Detection** - Automated threat detection and alerting

### 🛡️ **Security Features**
- **Rate Limiting** - Configurable throttling for all endpoints
- **CORS Support** - Cross-origin resource sharing with proper headers
- **Secure Headers** - HSTS, CSP, and other security headers
- **Input Validation** - Comprehensive request validation and sanitization
- **Error Handling** - Secure error responses without information leakage

### 🎨 **Modern UI/UX**
- **React 19** - Latest React with concurrent features
- **TypeScript** - Type-safe development
- **Tailwind CSS** - Utility-first styling
- **shadcn/ui** - Modern component library
- **Inertia.js** - SPA-like experience with server-side routing
- **Responsive Design** - Mobile-first, accessible interface

### 🏗️ **Architecture**
- **Clean Architecture** - Separation of concerns and testability
- **Repository Pattern** - Data access abstraction
- **Service Layer** - Business logic encapsulation
- **Queue System** - Asynchronous processing for metrics and notifications
- **Caching** - Redis/Memcached support for performance
- **Database Agnostic** - Supports MySQL, PostgreSQL, SQLite

## 🚀 Quick Start

### Prerequisites
- PHP 8.2 or higher
- Composer
- Node.js 20 or higher
- Database (MySQL, PostgreSQL, or SQLite)
- Redis (optional, for caching and queues)

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/your-username/laravel-oauth-provider.git
   cd laravel-oauth-provider
   ```

2. **Install PHP dependencies**
   ```bash
   composer install
   ```

3. **Install Node.js dependencies**
   ```bash
   npm install
   ```

4. **Environment setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

5. **Configure your database** in `.env`
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=oauth_provider
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   ```

6. **Run migrations and seeders**
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

7. **Generate OAuth2 keys**
   ```bash
   php artisan oauth:keys
   ```

8. **Build frontend assets**
   ```bash
   npm run build
   # or for development
   npm run dev
   ```

9. **Start the development server**
   ```bash
   composer run dev
   ```

The application will be available at `http://localhost:8000`.

## 📖 Documentation

### Quick Links
- [📋 Installation Guide](docs/installation.md)
- [⚙️ Configuration](docs/configuration.md)
- [🔧 OAuth2 Setup](docs/oauth2-setup.md)
- [📊 Monitoring & Metrics](docs/monitoring.md)
- [🛡️ Security](docs/security.md)
- [🚀 Deployment](docs/deployment.md)
- [🔌 API Reference](docs/api-reference.md)

### Core Concepts

#### OAuth2 Flow
This provider supports the standard OAuth2 authorization code flow with PKCE:

```
1. Client redirects user to /oauth/authorize
2. User authenticates and grants permissions
3. Authorization server redirects back with code
4. Client exchanges code for tokens at /oauth/token
5. Client uses access token to access protected resources
```

#### Endpoints
- **Authorization**: `/oauth/authorize`
- **Token**: `/oauth/token`
- **UserInfo**: `/oauth/userinfo` (OIDC)
- **Token Introspection**: `/oauth/introspect`
- **JWKS**: `/.well-known/jwks.json`
- **Discovery**: `/.well-known/openid-configuration`

## 🔧 Configuration

### OAuth2 Settings
```php
// config/oauth.php
return [
    'token_lifetime' => [
        'access_token' => 'PT1H',    // 1 hour
        'refresh_token' => 'P1M',    // 1 month
        'auth_code' => 'PT10M',      // 10 minutes
    ],
    
    'pkce' => [
        'required' => true,          // Require PKCE for all clients
    ],
    
    'scopes' => [
        'openid' => 'OpenID Connect',
        'profile' => 'User profile information',
        'email' => 'User email address',
    ],
];
```

### Metrics Configuration
```php
// config/oauth.php - metrics section
'metrics' => [
    'enabled' => true,
    'queue' => 'metrics',
    'retention_days' => 30,
],

'alerts' => [
    'thresholds' => [
        'response_time' => ['warning' => 500, 'critical' => 1000],
        'error_rate' => ['warning' => 5.0, 'critical' => 10.0],
        'failed_auth' => ['warning' => 10, 'critical' => 25],
    ],
],
```

## 📊 Monitoring

### Built-in Health Checks
```bash
# Check system health
php artisan oauth:health-check

# Send test alerts
php artisan oauth:health-check --test
```

### Metrics Dashboard
Access the metrics dashboard at `/admin/oauth-metrics` to view:
- Request volume and response times
- Error rates and types
- Client usage statistics
- Performance trends and alerts

### Available Metrics
- **Request Volume**: Requests per minute/hour/day
- **Response Times**: Average, P95, P99 response times
- **Error Rates**: 4xx/5xx error percentages
- **Authentication Events**: Success/failure rates
- **Client Analytics**: Usage per OAuth client
- **Security Events**: Suspicious activity detection

## 🛡️ Security

### Security Headers
```php
// Automatically applied security headers
'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
'X-Content-Type-Options' => 'nosniff',
'X-Frame-Options' => 'SAMEORIGIN',
'Referrer-Policy' => 'strict-origin-when-cross-origin',
```

### Rate Limiting
```php
// Per-endpoint rate limiting
'/oauth/token' => 'throttle:60,1',        // 60 requests per minute
'/oauth/authorize' => 'throttle:120,1',    // 120 requests per minute
'/oauth/userinfo' => 'throttle:300,1',     // 300 requests per minute
```

### Audit Logging
All critical OAuth events are automatically logged:
- Authorization grants
- Token issuance and refresh
- Client authentication attempts
- Permission changes
- Administrative actions

## 🚀 Deployment

### Production Checklist
- [ ] Environment variables configured
- [ ] Database migrations run
- [ ] OAuth2 keys generated
- [ ] Frontend assets built
- [ ] Queue workers running
- [ ] Scheduled tasks configured
- [ ] SSL certificate installed
- [ ] Rate limiting configured
- [ ] Monitoring setup

### Docker Support
```bash
# Build and run with Docker
docker-compose up -d

# Run migrations in container
docker-compose exec app php artisan migrate
```

### Queue Workers
```bash
# Start queue workers for metrics processing
php artisan queue:work --queue=metrics,default
```

## 🧪 Testing

### Run Tests
```bash
# Run all tests
composer test

# Run specific test suites
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit

# Run with coverage
php artisan test --coverage
```

### Test Coverage
- OAuth2 flow integration tests
- OIDC compliance tests
- Security vulnerability tests
- Performance benchmarks
- API endpoint tests

## 🤝 Contributing

We welcome contributions! Please read our [Contributing Guide](CONTRIBUTING.md) for details on:
- Code of conduct
- Development process
- Pull request guidelines
- Coding standards
- Testing requirements

### Development Setup
```bash
# Install development dependencies
composer install --dev
npm install --include=dev

# Run development tools
composer run dev    # Start dev servers
npm run lint       # Lint code
npm run type-check # TypeScript checking
```

## 📝 License

This project is open-sourced software licensed under the [MIT License](LICENSE).

## 🆘 Support

- **Documentation**: [docs/](docs/)
- **Issues**: [GitHub Issues](https://github.com/your-username/laravel-oauth-provider/issues)
- **Discussions**: [GitHub Discussions](https://github.com/your-username/laravel-oauth-provider/discussions)
- **Security**: Please report security vulnerabilities privately to [security@yourdomain.com](mailto:security@yourdomain.com)

## 🌟 Acknowledgments

This project builds upon excellent open-source libraries:
- [Laravel Framework](https://laravel.com) - The robust PHP framework
- [League OAuth2 Server](https://oauth2.thephpleague.com) - OAuth2 server implementation
- [React](https://reactjs.org) - Modern frontend library
- [Inertia.js](https://inertiajs.com) - Modern monolith approach
- [shadcn/ui](https://ui.shadcn.com) - Beautiful UI components

## 📊 Project Stats

![GitHub stars](https://img.shields.io/github/stars/your-username/laravel-oauth-provider?style=social)
![GitHub forks](https://img.shields.io/github/forks/your-username/laravel-oauth-provider?style=social)
![GitHub issues](https://img.shields.io/github/issues/your-username/laravel-oauth-provider)
![GitHub last commit](https://img.shields.io/github/last-commit/your-username/laravel-oauth-provider)

---

<p align="center">
  Made with ❤️ by the Laravel OAuth Provider Team
</p>