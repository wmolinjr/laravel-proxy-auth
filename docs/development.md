# 🛠️ Development Guide

This guide covers development setup and workflow using Laravel Sail for the OAuth2/OIDC Identity Provider.

## Prerequisites

- **Docker Desktop** or **Docker Engine** with Docker Compose
- **Git** for version control
- **VS Code** (recommended) with Docker extension

## Quick Start with Laravel Sail

Laravel Sail provides a simple command-line interface for interacting with Laravel's default Docker development environment.

### Initial Setup

1. **Clone the repository**
   ```bash
   git clone https://github.com/your-username/laravel-oauth-provider.git
   cd laravel-oauth-provider
   ```

2. **Install dependencies via Composer (without local PHP)**
   ```bash
   # Using Docker directly for first installation
   docker run --rm \
       -u "$(id -u):$(id -g)" \
       -v "$(pwd):/var/www/html" \
       -w /var/www/html \
       laravelsail/php83-composer:latest \
       composer install --ignore-platform-reqs
   ```

3. **Copy environment file**
   ```bash
   cp .env.example .env
   ```

4. **Start the development environment**
   ```bash
   ./vendor/bin/sail up -d
   ```

5. **Generate application key**
   ```bash
   ./vendor/bin/sail artisan key:generate
   ```

6. **Generate OAuth2 keys**
   ```bash
   ./vendor/bin/sail artisan oauth:keys
   ```

7. **Run database migrations**
   ```bash
   ./vendor/bin/sail artisan migrate
   ```

8. **Seed the database (optional)**
   ```bash
   ./vendor/bin/sail artisan db:seed
   ```

9. **Install Node.js dependencies**
   ```bash
   ./vendor/bin/sail npm install
   ```

10. **Build frontend assets**
    ```bash
    # For development
    ./vendor/bin/sail npm run dev
    
    # Or run in watch mode
    ./vendor/bin/sail npm run dev -- --watch
    ```

### Access Points

After running `sail up`, the following services will be available:

| Service | URL | Description |
|---------|-----|-------------|
| **OAuth Provider** | http://localhost | Main application |
| **Mailpit Dashboard** | http://localhost:8025 | Email testing interface |
| **Vite Dev Server** | http://localhost:5173 | Frontend hot reload |

### Database Access

| Service | Host | Port | Credentials |
|---------|------|------|-------------|
| **PostgreSQL** | localhost | 5432 | `oauth_user` / `secret` |
| **Redis** | localhost | 6379 | No password |

## Sail Commands

### Container Management

```bash
# Start all services
./vendor/bin/sail up -d

# Stop all services
./vendor/bin/sail down

# View logs
./vendor/bin/sail logs

# View specific service logs
./vendor/bin/sail logs laravel.test
./vendor/bin/sail logs pgsql
./vendor/bin/sail logs redis

# Restart services
./vendor/bin/sail restart
```

### Application Commands

```bash
# Artisan commands
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan tinker
./vendor/bin/sail artisan queue:work
./vendor/bin/sail artisan oauth:health-check

# Composer commands
./vendor/bin/sail composer install
./vendor/bin/sail composer require package/name
./vendor/bin/sail composer update

# NPM commands  
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev
./vendor/bin/sail npm run build
./vendor/bin/sail npm test
```

### Testing

```bash
# Run PHP tests
./vendor/bin/sail test

# Run specific test suite
./vendor/bin/sail test --testsuite=Feature
./vendor/bin/sail test --testsuite=Unit

# Run tests with coverage
./vendor/bin/sail test --coverage

# Run JavaScript tests
./vendor/bin/sail npm test
```

### Database Operations

```bash
# Run migrations
./vendor/bin/sail artisan migrate

# Rollback migrations
./vendor/bin/sail artisan migrate:rollback

# Fresh migration with seeding
./vendor/bin/sail artisan migrate:fresh --seed

# Database seeding
./vendor/bin/sail artisan db:seed

# Access PostgreSQL CLI
./vendor/bin/sail psql
```

### Queue Management

```bash
# Start queue worker
./vendor/bin/sail artisan queue:work

# Process specific queue
./vendor/bin/sail artisan queue:work --queue=metrics,default

# Monitor queue
./vendor/bin/sail artisan queue:monitor

# View failed jobs
./vendor/bin/sail artisan queue:failed
```

## Development Workflow

### 1. Daily Development

```bash
# Start development session
./vendor/bin/sail up -d

# Watch frontend changes
./vendor/bin/sail npm run dev

# In separate terminal - run queue worker
./vendor/bin/sail artisan queue:work

# Run tests before committing
./vendor/bin/sail test
```

### 2. Code Quality

```bash
# Format PHP code
./vendor/bin/sail composer format

# Format JavaScript/TypeScript
./vendor/bin/sail npm run format

# Run linting
./vendor/bin/sail composer lint
./vendor/bin/sail npm run lint

# Static analysis
./vendor/bin/sail composer analyse
```

### 3. Debugging

#### Xdebug Configuration

Enable Xdebug by modifying `.env`:

```env
SAIL_XDEBUG_MODE=develop,debug,coverage
SAIL_XDEBUG_CONFIG="client_host=host.docker.internal"
```

Restart Sail:
```bash
./vendor/bin/sail down
./vendor/bin/sail up -d
```

#### VS Code Setup

Create `.vscode/launch.json`:

```json
{
    "version": "0.2.0",
    "configurations": [
        {
            "name": "Listen for Xdebug (Sail)",
            "type": "php",
            "request": "launch",
            "port": 9003,
            "pathMappings": {
                "/var/www/html": "${workspaceFolder}"
            }
        }
    ]
}
```

## Environment Configuration

### Development Environment Variables

```env
# Application
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

# Laravel Sail
APP_PORT=80
VITE_PORT=5173

# Database  
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_DATABASE=oauth_provider
DB_USERNAME=oauth_user
DB_PASSWORD=secret

# Cache & Sessions
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Redis
REDIS_HOST=redis

# Mail (Mailpit)
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025

# OAuth Development Settings
OAUTH_ENABLE_METRICS=true
OAUTH_ENABLE_ALERTS=false
OAUTH_REQUIRE_PKCE=true
```

### Creating OAuth Clients for Testing

```bash
# Create a test client
./vendor/bin/sail artisan oauth:client \
    --name="Development Client" \
    --redirect="http://localhost:3000/callback" \
    --type=confidential

# Create a public client (SPA)
./vendor/bin/sail artisan oauth:client \
    --name="SPA Client" \
    --redirect="http://localhost:3000/callback" \
    --type=public
```

## Frontend Development

### Hot Module Replacement

```bash
# Start Vite dev server with HMR
./vendor/bin/sail npm run dev

# Access application with HMR
# http://localhost (proxy to Vite)
```

### Building Assets

```bash
# Development build
./vendor/bin/sail npm run build

# Production build
./vendor/bin/sail npm run build -- --mode=production

# Watch mode
./vendor/bin/sail npm run dev -- --watch
```

### TypeScript Development

```bash
# Type checking
./vendor/bin/sail npm run type-check

# Type checking in watch mode
./vendor/bin/sail npm run type-check -- --watch
```

## Testing

### PHP Testing

```bash
# Run all tests
./vendor/bin/sail test

# Run with coverage
./vendor/bin/sail test --coverage-html=storage/app/coverage

# Run specific test file
./vendor/bin/sail test tests/Feature/OAuth/TokenTest.php

# Run tests with specific filter
./vendor/bin/sail test --filter="test_can_issue_access_token"
```

### Frontend Testing

```bash
# Run JavaScript tests
./vendor/bin/sail npm test

# Run in watch mode
./vendor/bin/sail npm run test:watch

# Generate coverage report
./vendor/bin/sail npm run test:coverage
```

### Integration Testing

```bash
# Test OAuth flows
./vendor/bin/sail test tests/Feature/OAuth/

# Test API endpoints
./vendor/bin/sail test tests/Feature/Api/

# Test security features
./vendor/bin/sail test tests/Feature/Security/
```

## Performance Monitoring

### Metrics Dashboard

Access the metrics dashboard:
- **URL**: http://localhost/admin/oauth-metrics
- **Login**: Use seeded admin credentials

### Health Checks

```bash
# Run health check
./vendor/bin/sail artisan oauth:health-check

# Monitor system health
./vendor/bin/sail artisan oauth:health-check --monitor
```

### Queue Monitoring

```bash
# Monitor queue in real-time
./vendor/bin/sail artisan queue:monitor

# Check queue status
./vendor/bin/sail artisan queue:work --once
```

## Useful Aliases

Add to your shell profile (`.bashrc`, `.zshrc`):

```bash
# Laravel Sail alias
alias sail='./vendor/bin/sail'

# Common development commands
alias sup='sail up -d'
alias sdown='sail down'
alias sartisan='sail artisan'
alias scomposer='sail composer'
alias snpm='sail npm'
alias stest='sail test'
```

After adding aliases, reload your shell:
```bash
source ~/.bashrc  # or ~/.zshrc
```

## Troubleshooting

### Common Issues

#### 1. Port Already in Use
```bash
# Check what's using port 80
sudo lsof -i :80

# Change port in .env
APP_PORT=8080
```

#### 2. Permission Issues
```bash
# Fix storage permissions
sail artisan storage:link
sudo chown -R $USER:$USER storage bootstrap/cache
```

#### 3. Container Won't Start
```bash
# Clean up Docker
sail down -v
docker system prune -f
sail up -d
```

#### 4. Database Connection Issues
```bash
# Check PostgreSQL container
sail logs pgsql

# Reset database
sail artisan migrate:fresh --seed
```

#### 5. Assets Not Loading
```bash
# Clear compiled assets
sail npm run build
sail artisan view:clear
```

### Performance Tips

1. **Use Redis for sessions and cache**
2. **Enable OPcache in production**
3. **Use Queue workers for background tasks**
4. **Optimize Docker volumes** (bind mounts vs volumes)
5. **Limit Xdebug** to development only

### IDE Configuration

#### VS Code Extensions

Recommended extensions for development:

```json
{
  "recommendations": [
    "ms-vscode-remote.remote-containers",
    "felixfbecker.php-debug",
    "bmewburn.vscode-intelephense-client",
    "bradlc.vscode-tailwindcss",
    "ms-vscode.vscode-typescript-next"
  ]
}
```

#### PHPStorm Setup

1. Configure Docker integration
2. Set up Xdebug for Sail containers
3. Configure database connection to PostgreSQL
4. Set up code formatting with Laravel standards

## Production Considerations

### Environment Differences

| Setting | Development | Production |
|---------|-------------|------------|
| `APP_DEBUG` | `true` | `false` |
| `APP_ENV` | `local` | `production` |
| Cache Driver | `file/redis` | `redis` |
| Queue Driver | `sync/redis` | `redis` |
| Mail Driver | `mailpit` | `smtp` |

### Security in Development

Even in development, maintain security practices:

- Use strong database passwords
- Don't commit secrets to version control
- Regular dependency updates
- Test security features

## Contributing

### Development Setup for Contributors

1. Fork the repository
2. Follow the setup guide above
3. Create feature branches
4. Run tests before submitting PRs
5. Follow coding standards

### Code Standards

```bash
# Check PHP standards
./vendor/bin/sail composer lint

# Fix PHP formatting
./vendor/bin/sail composer format

# Check TypeScript standards
./vendor/bin/sail npm run lint

# Fix TypeScript formatting  
./vendor/bin/sail npm run format
```

This development guide provides everything needed to start contributing to the Laravel OAuth2/OIDC Identity Provider using Laravel Sail for a consistent development experience.