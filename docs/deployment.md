# 🚀 Deployment Guide

This guide covers deploying the Laravel OAuth2/OIDC Identity Provider to production environments.

## Production Requirements

### System Requirements
- **PHP**: 8.2+ with OPcache enabled
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **Database**: MySQL 8.0+, PostgreSQL 13+, or MariaDB 10.6+
- **Redis**: 7.0+ (recommended for sessions and caching)
- **SSL Certificate**: Valid HTTPS certificate (required)
- **Memory**: Minimum 1GB RAM, 2GB+ recommended
- **Storage**: 10GB+ available space

### PHP Extensions
```bash
# Required extensions
php -m | grep -E "(openssl|pdo|mbstring|tokenizer|xml|ctype|json|bcmath|curl)"

# Recommended extensions
php -m | grep -E "(redis|opcache|imagick|intl)"
```

## Apache Configuration

### Virtual Host Setup

Create `/etc/apache2/sites-available/oauth-provider.conf`:

```apache
<VirtualHost *:80>
    ServerName oauth.yourdomain.com
    DocumentRoot /var/www/oauth-provider/public
    
    # Redirect all HTTP to HTTPS
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]
    
    ErrorLog ${APACHE_LOG_DIR}/oauth-provider_error.log
    CustomLog ${APACHE_LOG_DIR}/oauth-provider_access.log combined
</VirtualHost>

<VirtualHost *:443>
    ServerName oauth.yourdomain.com
    DocumentRoot /var/www/oauth-provider/public
    
    # SSL Configuration
    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/oauth-provider.crt
    SSLCertificateKeyFile /etc/ssl/private/oauth-provider.key
    SSLCertificateChainFile /etc/ssl/certs/oauth-provider-chain.crt
    
    # Modern SSL Configuration
    SSLProtocol all -SSLv3 -TLSv1 -TLSv1.1
    SSLCipherSuite ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305
    SSLHonorCipherOrder off
    SSLSessionTickets off
    
    # OCSP Stapling
    SSLUseStapling On
    SSLStaplingCache "shmcb:logs/stapling-cache(150000)"
    
    # Security Headers
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "DENY"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' https://fonts.gstatic.com; connect-src 'self'"
    Header always set Permissions-Policy "camera=(), microphone=(), geolocation=(), payment=()"
    
    # Directory Configuration
    <Directory /var/www/oauth-provider/public>
        AllowOverride All
        Require all granted
        
        # Laravel Pretty URLs
        <IfModule mod_rewrite.c>
            RewriteEngine On
            RewriteCond %{REQUEST_FILENAME} !-f
            RewriteCond %{REQUEST_FILENAME} !-d
            RewriteRule ^(.*)$ index.php [QSA,L]
        </IfModule>
    </Directory>
    
    # Deny access to sensitive files
    <Files ".env">
        Require all denied
    </Files>
    
    <DirectoryMatch "/\.git">
        Require all denied
    </DirectoryMatch>
    
    # PHP Configuration
    <FilesMatch "\.php$">
        SetHandler "proxy:unix:/var/run/php/php8.3-fpm.sock|fcgi://localhost"
    </FilesMatch>
    
    # Static file caching
    <LocationMatch "\.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$">
        ExpiresActive On
        ExpiresDefault "access plus 1 year"
        Header append Cache-Control "public, immutable"
    </LocationMatch>
    
    # Logging
    ErrorLog ${APACHE_LOG_DIR}/oauth-provider_ssl_error.log
    CustomLog ${APACHE_LOG_DIR}/oauth-provider_ssl_access.log combined
    
    # Log Level for debugging (remove in production)
    # LogLevel info ssl:warn
</VirtualHost>
```

### Enable Required Modules

```bash
# Enable required Apache modules
sudo a2enmod rewrite
sudo a2enmod ssl
sudo a2enmod headers
sudo a2enmod expires
sudo a2enmod proxy
sudo a2enmod proxy_fcgi

# Enable the site
sudo a2ensite oauth-provider.conf
sudo a2dissite 000-default.conf

# Test configuration
sudo apache2ctl configtest

# Reload Apache
sudo systemctl reload apache2
```

### PHP-FPM Configuration

Edit `/etc/php/8.3/fpm/pool.d/oauth-provider.conf`:

```ini
[oauth-provider]
user = www-data
group = www-data

listen = /var/run/php/php8.3-fpm-oauth.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

pm = dynamic
pm.max_children = 20
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 10
pm.max_requests = 1000

; Logging
php_admin_value[error_log] = /var/log/php/oauth-provider-fpm.log
php_admin_flag[log_errors] = on

; Security
php_admin_value[disable_functions] = exec,passthru,shell_exec,system,proc_open,popen
php_admin_value[allow_url_fopen] = off
php_admin_value[allow_url_include] = off

; Performance
php_admin_value[max_execution_time] = 30
php_admin_value[max_input_time] = 60
php_admin_value[memory_limit] = 256M
php_admin_value[post_max_size] = 20M
php_admin_value[upload_max_filesize] = 10M

; OPcache
php_admin_value[opcache.enable] = 1
php_admin_value[opcache.memory_consumption] = 128
php_admin_value[opcache.interned_strings_buffer] = 8
php_admin_value[opcache.max_accelerated_files] = 4000
php_admin_value[opcache.revalidate_freq] = 2
php_admin_value[opcache.fast_shutdown] = 1
php_admin_value[opcache.validate_timestamps] = 0

; Session
php_admin_value[session.save_handler] = redis
php_admin_value[session.save_path] = "tcp://127.0.0.1:6379"
php_admin_value[session.gc_maxlifetime] = 1440
```

## Nginx Configuration

### Server Block Setup

Create `/etc/nginx/sites-available/oauth-provider`:

```nginx
# Redirect HTTP to HTTPS
server {
    listen 80;
    listen [::]:80;
    server_name oauth.yourdomain.com;
    
    # Security headers even for redirects
    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options DENY;
    
    return 301 https://$server_name$request_uri;
}

# HTTPS Server
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name oauth.yourdomain.com;
    
    root /var/www/oauth-provider/public;
    index index.php;
    
    # SSL Configuration
    ssl_certificate /etc/ssl/certs/oauth-provider.crt;
    ssl_certificate_key /etc/ssl/private/oauth-provider.key;
    ssl_trusted_certificate /etc/ssl/certs/oauth-provider-chain.crt;
    
    # Modern SSL Configuration
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;
    ssl_session_tickets off;
    
    # OCSP stapling
    ssl_stapling on;
    ssl_stapling_verify on;
    resolver 8.8.8.8 8.8.4.4 valid=300s;
    resolver_timeout 5s;
    
    # Security Headers
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "DENY" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' https://fonts.gstatic.com; connect-src 'self'" always;
    add_header Permissions-Policy "camera=(), microphone=(), geolocation=(), payment=()" always;
    
    # Gzip Compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/xml text/javascript application/javascript application/xml+rss application/json;
    
    # Main location block
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    # PHP handling
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        
        # Security
        fastcgi_param HTTP_PROXY "";
        fastcgi_hide_header X-Powered-By;
        
        # Buffer settings
        fastcgi_buffer_size 128k;
        fastcgi_buffers 4 256k;
        fastcgi_busy_buffers_size 256k;
        
        # Timeouts
        fastcgi_connect_timeout 60s;
        fastcgi_send_timeout 180s;
        fastcgi_read_timeout 180s;
    }
    
    # Static file handling with caching
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        add_header X-Content-Type-Options "nosniff" always;
        try_files $uri =404;
    }
    
    # Deny access to sensitive files
    location ~ /\.env {
        deny all;
        return 404;
    }
    
    location ~ /\.git {
        deny all;
        return 404;
    }
    
    location ~ /storage {
        deny all;
        return 404;
    }
    
    location ~ /bootstrap/cache {
        deny all;
        return 404;
    }
    
    # OAuth specific optimizations
    location ~ ^/oauth/(token|authorize|userinfo|introspect) {
        try_files $uri /index.php?$query_string;
        
        # Rate limiting (requires nginx-module-limit-req)
        limit_req zone=oauth burst=20 nodelay;
        limit_req_status 429;
    }
    
    # Well-known endpoints with caching
    location ~ ^/\.well-known/ {
        try_files $uri /index.php?$query_string;
        expires 1h;
        add_header Cache-Control "public";
    }
    
    # Logging
    access_log /var/log/nginx/oauth-provider_access.log;
    error_log /var/log/nginx/oauth-provider_error.log;
}

# Rate limiting zones
http {
    limit_req_zone $binary_remote_addr zone=oauth:10m rate=60r/m;
}
```

Enable the site:

```bash
sudo ln -s /etc/nginx/sites-available/oauth-provider /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

## Database Setup

### MySQL/MariaDB Configuration

```sql
-- Create database and user
CREATE DATABASE oauth_provider CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'oauth_user'@'localhost' IDENTIFIED BY 'secure_password_here';
GRANT ALL PRIVILEGES ON oauth_provider.* TO 'oauth_user'@'localhost';
FLUSH PRIVILEGES;
```

Optimize MySQL configuration in `/etc/mysql/mysql.conf.d/mysqld.cnf`:

```ini
[mysqld]
# Performance
innodb_buffer_pool_size = 1G
innodb_buffer_pool_instances = 4
innodb_log_file_size = 256M
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT

# Connections
max_connections = 200
max_connect_errors = 10000
max_allowed_packet = 64M

# Query cache (MySQL 5.7 and below)
query_cache_type = 1
query_cache_size = 64M
query_cache_limit = 2M

# Logging
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 2
log_queries_not_using_indexes = 1

# Security
bind-address = 127.0.0.1
skip-networking = 0
```

### PostgreSQL Configuration

```sql
-- Create database and user
CREATE DATABASE oauth_provider WITH ENCODING 'UTF8' LC_COLLATE='en_US.UTF-8' LC_CTYPE='en_US.UTF-8';
CREATE USER oauth_user WITH PASSWORD 'secure_password_here';
GRANT ALL PRIVILEGES ON DATABASE oauth_provider TO oauth_user;
```

Optimize PostgreSQL in `/etc/postgresql/15/main/postgresql.conf`:

```conf
# Memory
shared_buffers = 256MB
effective_cache_size = 1GB
work_mem = 4MB
maintenance_work_mem = 64MB

# Checkpoints
checkpoint_completion_target = 0.9
wal_buffers = 16MB

# Query planner
random_page_cost = 1.1
effective_io_concurrency = 200

# Logging
log_min_duration_statement = 1000
log_line_prefix = '%t [%p]: [%l-1] user=%u,db=%d '
log_checkpoints = on
log_connections = on
log_disconnections = on
log_lock_waits = on
```

## Redis Configuration

Configure Redis in `/etc/redis/redis.conf`:

```conf
# Network
bind 127.0.0.1
port 6379
protected-mode yes

# Security
requirepass your_redis_password_here

# Memory
maxmemory 512mb
maxmemory-policy allkeys-lru

# Persistence
save 900 1
save 300 10
save 60 10000

# AOF
appendonly yes
appendfsync everysec

# Slow log
slowlog-log-slower-than 10000
slowlog-max-len 128
```

## Application Deployment

### Directory Structure

```bash
# Create application directory
sudo mkdir -p /var/www/oauth-provider
sudo chown -R $USER:www-data /var/www/oauth-provider

# Set proper permissions
find /var/www/oauth-provider -type f -exec chmod 644 {} \;
find /var/www/oauth-provider -type d -exec chmod 755 {} \;
chmod -R 775 /var/www/oauth-provider/storage
chmod -R 775 /var/www/oauth-provider/bootstrap/cache
chmod +x /var/www/oauth-provider/artisan
```

### Deployment Script

Create `deploy.sh`:

```bash
#!/bin/bash

set -e

echo "🚀 Starting OAuth Provider deployment..."

# Variables
APP_DIR="/var/www/oauth-provider"
REPO_URL="https://github.com/your-username/laravel-oauth-provider.git"
BRANCH="main"

# Check if first deployment
if [ ! -d "$APP_DIR/.git" ]; then
    echo "📦 First deployment - cloning repository..."
    git clone -b $BRANCH $REPO_URL $APP_DIR
else
    echo "🔄 Updating existing deployment..."
    cd $APP_DIR
    git fetch origin
    git reset --hard origin/$BRANCH
fi

cd $APP_DIR

# Install/update dependencies
echo "📚 Installing dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction
npm ci --omit=dev

# Generate keys if not exist
if [ ! -f "storage/oauth-private.key" ]; then
    echo "🔐 Generating OAuth keys..."
    php artisan oauth:keys
fi

# Build assets
echo "🏗️  Building frontend assets..."
npm run build

# Cache configuration
echo "⚡ Optimizing application..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Run migrations
echo "🗃️  Running database migrations..."
php artisan migrate --force

# Clear old cache
php artisan cache:clear
php artisan queue:restart

# Set permissions
echo "🔒 Setting permissions..."
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

echo "✅ Deployment completed successfully!"
```

Make it executable:

```bash
chmod +x deploy.sh
```

### Environment Configuration

Create production `.env`:

```env
APP_NAME="OAuth Provider"
APP_ENV=production
APP_KEY=base64:your-generated-key
APP_DEBUG=false
APP_URL=https://oauth.yourdomain.com

LOG_CHANNEL=daily
LOG_LEVEL=info
LOG_DAYS=14

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=oauth_provider
DB_USERNAME=oauth_user
DB_PASSWORD=your-secure-password

BROADCAST_DRIVER=log
CACHE_DRIVER=redis
FILESYSTEM_DISK=local
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=your-redis-password
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=smtp.yourdomain.com
MAIL_PORT=587
MAIL_USERNAME=oauth@yourdomain.com
MAIL_PASSWORD=your-mail-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=oauth@yourdomain.com
MAIL_FROM_NAME="OAuth Provider"

# OAuth2 Configuration
OAUTH_PRIVATE_KEY_PATH="storage/oauth-private.key"
OAUTH_PUBLIC_KEY_PATH="storage/oauth-public.key"
OAUTH_PASSPHRASE=null
OAUTH_ACCESS_TOKEN_LIFETIME="PT1H"
OAUTH_REFRESH_TOKEN_LIFETIME="P30D"
OAUTH_AUTH_CODE_LIFETIME="PT10M"
OAUTH_REQUIRE_PKCE=true

# Metrics & Monitoring
OAUTH_ENABLE_METRICS=true
OAUTH_ENABLE_ALERTS=true
OAUTH_METRICS_RETENTION_DAYS=90

# Security
CORS_ALLOWED_ORIGINS="https://yourapp.com,https://anotherapp.com"
OAUTH_RATE_LIMITING_ENABLED=true

# Admin Configuration
OAUTH_ADMIN_EMAIL=admin@yourdomain.com
```

## Process Management

### Supervisor Configuration

Install and configure Supervisor for queue workers:

```bash
sudo apt install supervisor
```

Create `/etc/supervisor/conf.d/oauth-provider-worker.conf`:

```ini
[program:oauth-provider-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/oauth-provider/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/oauth-provider/storage/logs/worker.log
stopwaitsecs=3600
```

Start the workers:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start oauth-provider-worker:*
```

### Systemd Services

Create custom systemd services for better control:

`/etc/systemd/system/oauth-provider-queue.service`:

```ini
[Unit]
Description=OAuth Provider Queue Worker
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
Restart=always
RestartSec=5s
ExecStart=/usr/bin/php /var/www/oauth-provider/artisan queue:work --sleep=3 --tries=3 --max-time=3600
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
```

Enable and start:

```bash
sudo systemctl daemon-reload
sudo systemctl enable oauth-provider-queue
sudo systemctl start oauth-provider-queue
```

## Monitoring and Maintenance

### Cron Jobs

Add to `/etc/crontab`:

```cron
# Laravel Scheduler
* * * * * www-data cd /var/www/oauth-provider && php artisan schedule:run >> /dev/null 2>&1

# Cleanup old logs daily at 2 AM
0 2 * * * www-data cd /var/www/oauth-provider && php artisan oauth:cleanup-logs >> /dev/null 2>&1

# Health check every 5 minutes
*/5 * * * * www-data cd /var/www/oauth-provider && php artisan oauth:health-check >> /dev/null 2>&1

# Backup database daily at 3 AM
0 3 * * * root /usr/local/bin/backup-oauth-db.sh >> /var/log/backup.log 2>&1
```

### Backup Script

Create `/usr/local/bin/backup-oauth-db.sh`:

```bash
#!/bin/bash

BACKUP_DIR="/var/backups/oauth-provider"
DATE=$(date +%Y%m%d_%H%M%S)
DB_NAME="oauth_provider"
DB_USER="oauth_user"
DB_PASS="your-secure-password"

mkdir -p $BACKUP_DIR

# Database backup
mysqldump -u$DB_USER -p$DB_PASS $DB_NAME > $BACKUP_DIR/db_backup_$DATE.sql

# Compress backup
gzip $BACKUP_DIR/db_backup_$DATE.sql

# Keep only last 30 days
find $BACKUP_DIR -name "db_backup_*.sql.gz" -mtime +30 -delete

echo "Backup completed: $BACKUP_DIR/db_backup_$DATE.sql.gz"
```

### Log Rotation

Configure logrotate in `/etc/logrotate.d/oauth-provider`:

```
/var/www/oauth-provider/storage/logs/*.log {
    daily
    missingok
    rotate 52
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
    postrotate
        php /var/www/oauth-provider/artisan config:clear > /dev/null 2>&1 || true
    endscript
}
```

## Security Hardening

### Firewall Configuration

```bash
# UFW rules
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow ssh
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable

# Fail2ban for additional protection
sudo apt install fail2ban
```

Configure Fail2ban in `/etc/fail2ban/jail.local`:

```ini
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 5

[nginx-http-auth]
enabled = true
port = http,https
logpath = /var/log/nginx/*error.log

[nginx-limit-req]
enabled = true
port = http,https
logpath = /var/log/nginx/*error.log
maxretry = 10

[oauth-provider]
enabled = true
port = http,https
logpath = /var/www/oauth-provider/storage/logs/laravel.log
maxretry = 5
findtime = 300
bantime = 1800
```

### File Permissions

```bash
# Set secure permissions
sudo find /var/www/oauth-provider -type f -exec chmod 644 {} \;
sudo find /var/www/oauth-provider -type d -exec chmod 755 {} \;
sudo chmod -R 775 /var/www/oauth-provider/storage
sudo chmod -R 775 /var/www/oauth-provider/bootstrap/cache
sudo chmod 600 /var/www/oauth-provider/.env
sudo chmod 600 /var/www/oauth-provider/storage/oauth-*.key
```

## Performance Optimization

### OPcache Configuration

Edit `/etc/php/8.3/fpm/conf.d/10-opcache.ini`:

```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.revalidate_freq=0
opcache.validate_timestamps=0
opcache.save_comments=1
opcache.fast_shutdown=1
```

### Application Optimizations

```bash
# Production optimizations
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Optimize Composer autoloader
composer dump-autoload --optimize --classmap-authoritative
```

## Troubleshooting

### Common Issues

1. **Permission Errors**
   ```bash
   sudo chown -R www-data:www-data storage bootstrap/cache
   sudo chmod -R 775 storage bootstrap/cache
   ```

2. **Cache Issues**
   ```bash
   php artisan cache:clear
   php artisan config:clear
   php artisan route:clear
   php artisan view:clear
   ```

3. **Queue Not Processing**
   ```bash
   sudo supervisorctl restart oauth-provider-worker:*
   # or
   sudo systemctl restart oauth-provider-queue
   ```

4. **SSL Certificate Issues**
   ```bash
   # Test SSL configuration
   openssl s_client -connect oauth.yourdomain.com:443 -servername oauth.yourdomain.com
   ```

### Health Checks

```bash
# Application health
php artisan oauth:health-check

# Database connection
php artisan tinker
>>> DB::connection()->getPdo();

# Redis connection
>>> Redis::ping();

# Queue status
php artisan queue:monitor
```

This deployment guide provides a comprehensive setup for production environments. Adjust configurations based on your specific requirements and infrastructure.