# 📋 Installation Guide

This guide will walk you through setting up the Laravel OAuth2/OIDC Identity Provider on your local development environment or production server.

## System Requirements

### Minimum Requirements
- **PHP**: 8.2 or higher
- **Composer**: 2.0 or higher
- **Node.js**: 20.x or higher
- **NPM**: 10.x or higher
- **Database**: MySQL 8.0+, PostgreSQL 13+, or SQLite 3.8+

### Recommended Requirements
- **PHP**: 8.3+ with OPcache enabled
- **Memory**: 512MB+ for development, 1GB+ for production
- **Storage**: 2GB+ available space
- **Redis**: 7.0+ (for caching and queues)

### PHP Extensions
Ensure the following PHP extensions are installed:
```bash
# Required extensions
php -m | grep -E "(openssl|pdo|mbstring|tokenizer|xml|ctype|json|bcmath|curl|gd)"

# Optional but recommended
php -m | grep -E "(redis|imagick|intl)"
```

## Installation Methods

### Method 1: Clone Repository (Recommended)

1. **Clone the repository**
   ```bash
   git clone https://github.com/your-username/laravel-oauth-provider.git
   cd laravel-oauth-provider
   ```

2. **Install dependencies**
   ```bash
   # Install PHP dependencies
   composer install --optimize-autoloader --no-dev

   # Install Node.js dependencies
   npm ci --omit=dev
   ```

3. **Setup environment**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

### Method 2: Composer Create-Project

```bash
composer create-project your-username/laravel-oauth-provider oauth-provider
cd oauth-provider
```

## Environment Configuration

### Database Configuration

#### MySQL
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=oauth_provider
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

#### PostgreSQL
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=oauth_provider
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

#### SQLite (Development Only)
```env
DB_CONNECTION=sqlite
DB_DATABASE=/path/to/database.sqlite
```

### Redis Configuration (Optional)
```env
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DB=0

CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
```

### OAuth2 Configuration
```env
# OAuth2 Settings
OAUTH_PRIVATE_KEY_PATH="storage/oauth-private.key"
OAUTH_PUBLIC_KEY_PATH="storage/oauth-public.key"
OAUTH_PASSPHRASE=null

# Token Lifetimes (ISO 8601 duration format)
OAUTH_ACCESS_TOKEN_LIFETIME="PT1H"
OAUTH_REFRESH_TOKEN_LIFETIME="P1M"
OAUTH_AUTH_CODE_LIFETIME="PT10M"

# Security
OAUTH_REQUIRE_PKCE=true
OAUTH_ENABLE_METRICS=true
```

### Mail Configuration
```env
MAIL_MAILER=smtp
MAIL_HOST=mailhog
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@yourdomain.com"
MAIL_FROM_NAME="OAuth Provider"
```

## Database Setup

### Create Database
```bash
# MySQL
mysql -u root -p -e "CREATE DATABASE oauth_provider CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# PostgreSQL
createdb -U postgres oauth_provider
```

### Run Migrations
```bash
php artisan migrate
```

### Seed Database (Optional)
```bash
php artisan db:seed
```

This will create:
- Default admin user (`admin@example.com` / `password`)
- Sample OAuth clients
- Default permissions and roles

## OAuth2 Keys Generation

Generate RSA keys for JWT token signing:

```bash
# Generate private key
openssl genrsa -out storage/oauth-private.key 4096

# Generate public key
openssl rsa -in storage/oauth-private.key -pubout -out storage/oauth-public.key

# Set proper permissions
chmod 600 storage/oauth-private.key
chmod 644 storage/oauth-public.key
```

Or use the Artisan command:
```bash
php artisan oauth:keys
```

## Frontend Assets

### Development Build
```bash
npm run dev
```

### Production Build
```bash
npm run build
```

### Watch Mode (Development)
```bash
npm run dev
# Keep this running in a separate terminal
```

## Queue Setup

### Database Queues
```bash
php artisan queue:table
php artisan migrate
```

### Start Queue Workers
```bash
# Start all queues
php artisan queue:work

# Start specific queue
php artisan queue:work --queue=metrics,default

# Production daemon
php artisan queue:work --daemon --sleep=3 --tries=3
```

### Supervisor Configuration (Production)
```ini
[program:oauth-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/oauth-provider/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/oauth-provider/storage/logs/queue.log
stopwaitsecs=3600
```

## Scheduled Tasks

Add to your crontab:
```bash
* * * * * cd /path/to/oauth-provider && php artisan schedule:run >> /dev/null 2>&1
```

## Web Server Configuration

### Apache
```apache
<VirtualHost *:80>
    ServerName oauth-provider.local
    DocumentRoot /path/to/oauth-provider/public
    
    <Directory /path/to/oauth-provider/public>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/oauth-provider_error.log
    CustomLog ${APACHE_LOG_DIR}/oauth-provider_access.log combined
</VirtualHost>
```

### Nginx
```nginx
server {
    listen 80;
    server_name oauth-provider.local;
    root /path/to/oauth-provider/public;
    
    index index.php;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

## File Permissions

Set proper permissions:
```bash
# Set directory permissions
find /path/to/oauth-provider -type d -exec chmod 755 {} \;

# Set file permissions
find /path/to/oauth-provider -type f -exec chmod 644 {} \;

# Storage and cache directories
chmod -R 775 storage
chmod -R 775 bootstrap/cache

# Make artisan executable
chmod +x artisan
```

## Verification

### Test Installation
```bash
# Check system status
php artisan about

# Run health check
php artisan oauth:health-check

# Test OAuth endpoints
curl -I http://oauth-provider.local/.well-known/openid-configuration
```

### Access Application
- **Frontend**: http://oauth-provider.local
- **Admin Dashboard**: http://oauth-provider.local/admin
- **Metrics**: http://oauth-provider.local/admin/oauth-metrics

### Default Credentials
```
Email: admin@example.com
Password: password
```

**⚠️ Change the default credentials immediately!**

## Troubleshooting

### Common Issues

#### 1. Permission Denied
```bash
sudo chown -R www-data:www-data /path/to/oauth-provider
sudo chmod -R 775 storage bootstrap/cache
```

#### 2. Key Permissions
```bash
chmod 600 storage/oauth-private.key
chmod 644 storage/oauth-public.key
```

#### 3. Database Connection
```bash
# Test database connection
php artisan tinker
>>> DB::connection()->getPdo();
```

#### 4. Redis Connection
```bash
# Test Redis connection
php artisan tinker
>>> Redis::ping();
```

#### 5. Clear Caches
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

### Debug Mode
Enable debug mode for development:
```env
APP_DEBUG=true
APP_LOG_LEVEL=debug
```

**⚠️ Never enable debug mode in production!**

## Docker Installation

### Using Docker Compose

1. **Clone repository**
   ```bash
   git clone https://github.com/your-username/laravel-oauth-provider.git
   cd laravel-oauth-provider
   ```

2. **Build and start containers**
   ```bash
   docker-compose up -d
   ```

3. **Install dependencies**
   ```bash
   docker-compose exec app composer install
   docker-compose exec app npm install
   ```

4. **Setup application**
   ```bash
   docker-compose exec app php artisan key:generate
   docker-compose exec app php artisan migrate
   docker-compose exec app php artisan oauth:keys
   docker-compose exec app npm run build
   ```

## Next Steps

After successful installation:

1. [📖 Read the Configuration Guide](configuration.md)
2. [🔧 Setup your first OAuth2 client](oauth2-setup.md)
3. [📊 Configure monitoring](monitoring.md)
4. [🚀 Prepare for deployment](deployment.md)

## Getting Help

If you encounter issues:
- Check the [Troubleshooting Section](#troubleshooting)
- Review the [FAQ](faq.md)
- Open an issue on [GitHub](https://github.com/your-username/laravel-oauth-provider/issues)