# 🔧 Apache Configuration Guide

This guide shows how to configure Apache to work with the Laravel OAuth2/OIDC Identity Provider, based on a working production setup.

## Prerequisites

### Required Apache Modules

Enable the required Apache modules:

```bash
# Enable core modules
sudo a2enmod rewrite
sudo a2enmod ssl  
sudo a2enmod headers
sudo a2enmod proxy
sudo a2enmod proxy_fcgi
sudo a2enmod remoteip

# For client applications using OIDC
sudo apt-get install libapache2-mod-auth-openidc
sudo a2enmod auth_openidc

# Restart Apache
sudo systemctl restart apache2
```

## OAuth Provider (Server) Configuration

### Virtual Host for OAuth Provider

Create `/etc/apache2/sites-available/oauth-provider.conf`:

```apache
# HTTP Virtual Host - Redirects to HTTPS
<VirtualHost *:80>
    ServerName oauth.yourdomain.com
    Redirect permanent / https://oauth.yourdomain.com/
</VirtualHost>

# HTTPS Virtual Host - OAuth Provider
<VirtualHost *:443>
    ServerName oauth.yourdomain.com
    DocumentRoot /var/www/oauth-provider/public
    
    # Logging
    ErrorLog ${APACHE_LOG_DIR}/oauth-provider_error.log
    CustomLog ${APACHE_LOG_DIR}/oauth-provider_access.log combined
    
    # Directory Configuration
    <Directory /var/www/oauth-provider/public>
        Options Indexes FollowSymLinks MultiViews
        AllowOverride All
        Require all granted
        
        # Laravel Pretty URLs
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteRule ^ index.php [L]
    </Directory>
    
    # Security Headers
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
    
    # CORS Headers for OIDC
    Header always set Access-Control-Allow-Origin "*"
    Header always set Access-Control-Allow-Methods "GET, POST, OPTIONS"
    Header always set Access-Control-Allow-Headers "Content-Type, Authorization"
    
    # PHP-FPM Configuration
    RewriteEngine on
    RemoveHandler .php
    <FilesMatch \.php$>
        SetHandler proxy:unix:/run/php/php8.3-fpm.sock|fcgi://127.0.0.1
    </FilesMatch>
    
    # SSL Configuration
    SSLEngine on
    SSLCertificateFile /path/to/your/ssl.cert
    SSLCertificateKeyFile /path/to/your/ssl.key
    SSLCertificateChainFile /path/to/your/ssl.ca
    
    # Modern SSL Configuration
    SSLProtocol all -SSLv2 -SSLv3 -TLSv1 -TLSv1.1
    SSLCipherSuite ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384
    SSLHonorCipherOrder off
    
    # Deny access to sensitive files
    <Files ".env">
        Require all denied
    </Files>
    
    <DirectoryMatch "/\.git">
        Require all denied
    </DirectoryMatch>
</VirtualHost>
```

Enable the site:

```bash
sudo a2ensite oauth-provider.conf
sudo systemctl reload apache2
```

## Client Application Configuration

### OIDC Client Virtual Host

For applications that need to authenticate against your OAuth provider:

```apache
<VirtualHost *:80>
    ServerName myapp.yourdomain.com
    DocumentRoot /var/www/myapp/public
    
    # Redirect to HTTPS
    RewriteEngine On
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]
</VirtualHost>

<VirtualHost *:443>
    ServerName myapp.yourdomain.com
    DocumentRoot /var/www/myapp/public
    
    # Logging
    ErrorLog ${APACHE_LOG_DIR}/myapp_error.log
    CustomLog ${APACHE_LOG_DIR}/myapp_access.log combined
    
    # OpenID Connect Configuration
    OIDCProviderMetadataURL https://oauth.yourdomain.com/.well-known/openid-configuration
    OIDCClientID your-client-id-here
    OIDCClientSecret your-client-secret-here
    OIDCRedirectURI https://myapp.yourdomain.com/oidc-redirect
    
    # Crypto settings (generate random passphrase)
    OIDCCryptoPassphrase your-random-crypto-passphrase-here
    
    # Cache configuration
    OIDCCacheType shm
    OIDCScope "openid profile email"
    OIDCResponseType "code"
    
    # Timeout configuration
    OIDCStateTimeout 3600
    OIDCSessionMaxDuration 3600
    OIDCSessionInactivityTimeout 3600
    
    # Token endpoint authentication method
    OIDCProviderTokenEndpointAuth client_secret_post
    
    # Debug logging (remove in production)
    LogLevel auth_openidc:info
    
    # Main protection - Protects entire application
    <Location "/">
        AuthType openid-connect
        Require valid-user
    </Location>
    
    # OAuth callback endpoint
    <Location "/oidc-redirect">
        AuthType openid-connect
        Require valid-user
    </Location>
    
    # Allow public access to well-known endpoints
    <Location "/.well-known">
        Require all granted
    </Location>
    
    # Static files - no authentication needed
    <Location "/favicon.ico">
        Require all granted
    </Location>
    
    <LocationMatch "^/(css|js|images|fonts|assets|static)/.*">
        Require all granted
    </LocationMatch>
    
    # If proxying to backend application
    ProxyPreserveHost On
    ProxyPass /.well-known !
    ProxyPass /oidc-redirect !
    ProxyPass / http://127.0.0.1:3000/
    ProxyPassReverse / http://127.0.0.1:3000/
    
    # WebSocket support (if needed)
    RewriteEngine On
    RewriteCond %{HTTP:UPGRADE} ^WebSocket$ [NC]
    RewriteCond %{HTTP:CONNECTION} ^Upgrade$ [NC]
    RewriteRule ^/?(.*) "ws://127.0.0.1:3000/$1" [P]
    
    # Pass user information to backend as headers
    RequestHeader set "X-Remote-User" "%{OIDC_CLAIM_sub}e"
    RequestHeader set "X-Remote-User-Email" "%{OIDC_CLAIM_email}e"
    RequestHeader set "X-Remote-User-Name" "%{OIDC_CLAIM_name}e"
    RequestHeader set "X-Remote-User-Groups" "%{OIDC_CLAIM_groups}e"
    
    # SSL Configuration
    SSLEngine on
    SSLCertificateFile /path/to/your/ssl.cert
    SSLCertificateKeyFile /path/to/your/ssl.key
    SSLCertificateChainFile /path/to/your/ssl.ca
    SSLProtocol all -SSLv2 -SSLv3 -TLSv1 -TLSv1.1
</VirtualHost>
```

## PHP-FPM Configuration

### Pool Configuration

Create `/etc/php/8.3/fpm/pool.d/oauth-provider.conf`:

```ini
[oauth-provider]
user = www-data
group = www-data

listen = /run/php/php8.3-fpm.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

pm = dynamic
pm.max_children = 20
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 10
pm.max_requests = 1000

; Security
php_admin_value[disable_functions] = exec,passthru,shell_exec,system
php_admin_value[allow_url_fopen] = off
php_admin_value[allow_url_include] = off

; Performance
php_admin_value[max_execution_time] = 30
php_admin_value[memory_limit] = 256M
php_admin_value[opcache.enable] = 1
php_admin_value[opcache.memory_consumption] = 128
php_admin_value[opcache.validate_timestamps] = 0

; Session (if using Redis)
php_admin_value[session.save_handler] = redis
php_admin_value[session.save_path] = "tcp://127.0.0.1:6379"
```

Restart PHP-FPM:

```bash
sudo systemctl restart php8.3-fpm
```

## SSL Certificate Setup

### Using Let's Encrypt (Recommended)

```bash
# Install Certbot
sudo apt install certbot python3-certbot-apache

# Get certificate for OAuth provider
sudo certbot --apache -d oauth.yourdomain.com

# Get certificate for client application
sudo certbot --apache -d myapp.yourdomain.com

# Auto-renewal is set up automatically
```

### Using Self-Signed Certificates (Development)

```bash
# Generate self-signed certificate
sudo openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout /etc/ssl/private/oauth-provider.key \
    -out /etc/ssl/certs/oauth-provider.crt \
    -subj "/C=US/ST=State/L=City/O=Organization/OU=Department/CN=oauth.yourdomain.com"
```

## Security Configuration

### Additional Security Headers

Add to your virtual host:

```apache
# Security Headers
Header always set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' https://fonts.gstatic.com"
Header always set Permissions-Policy "camera=(), microphone=(), geolocation=()"
Header always set X-XSS-Protection "1; mode=block"

# Hide Apache version
ServerTokens Prod
ServerSignature Off
```

### Rate Limiting with mod_evasive

```bash
# Install mod_evasive
sudo apt install libapache2-mod-evasive
sudo a2enmod evasive

# Configure in /etc/apache2/mods-enabled/evasive.conf
<IfModule mod_evasive24.c>
    DOSHashTableSize    32768
    DOSPageCount        3
    DOSPageInterval     1
    DOSSiteCount        50
    DOSSiteInterval     1
    DOSBlockingPeriod   600
</IfModule>
```

## Firewall Configuration

### UFW Rules

```bash
# Allow HTTP and HTTPS
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Allow SSH (if needed)
sudo ufw allow 22/tcp

# Enable firewall
sudo ufw enable
```

## Monitoring and Logs

### Log Configuration

Monitor these log files:

```bash
# OAuth Provider logs
tail -f /var/log/apache2/oauth-provider_error.log
tail -f /var/log/apache2/oauth-provider_access.log

# Client application logs
tail -f /var/log/apache2/myapp_error.log
tail -f /var/log/apache2/myapp_access.log

# OIDC module logs (with debug enabled)
tail -f /var/log/apache2/error.log | grep auth_openidc
```

### Logrotate Configuration

Create `/etc/logrotate.d/oauth-provider`:

```
/var/log/apache2/oauth-provider*.log {
    daily
    missingok
    rotate 52
    compress
    delaycompress
    notifempty
    create 644 root adm
    postrotate
        /bin/systemctl reload apache2 > /dev/null 2>/dev/null || true
    endscript
}
```

## Testing Configuration

### Test OAuth Provider

```bash
# Test well-known endpoint
curl -I https://oauth.yourdomain.com/.well-known/openid-configuration

# Should return 200 OK with JSON content
```

### Test Client Application

```bash
# Test redirect to OAuth provider
curl -I https://myapp.yourdomain.com/

# Should return 302 redirect to OAuth provider
```

### Test SSL Configuration

```bash
# Test SSL certificate
openssl s_client -connect oauth.yourdomain.com:443 -servername oauth.yourdomain.com

# Test with SSL Labs (online)
# https://www.ssllabs.com/ssltest/analyze.html?d=oauth.yourdomain.com
```

## Troubleshooting

### Common Issues

1. **OIDC Module Not Working**
   ```bash
   # Check if module is loaded
   sudo apache2ctl -M | grep auth_openidc
   
   # Enable module if missing
   sudo a2enmod auth_openidc
   sudo systemctl restart apache2
   ```

2. **PHP-FPM Socket Permission Issues**
   ```bash
   # Check socket permissions
   ls -la /run/php/php8.3-fpm.sock
   
   # Fix permissions if needed
   sudo chown www-data:www-data /run/php/php8.3-fpm.sock
   ```

3. **SSL Certificate Issues**
   ```bash
   # Test certificate validity
   sudo openssl x509 -in /path/to/ssl.cert -text -noout
   
   # Renew Let's Encrypt certificate
   sudo certbot renew --dry-run
   ```

4. **CORS Issues**
   ```bash
   # Add CORS headers to OAuth provider
   Header always set Access-Control-Allow-Origin "*"
   Header always set Access-Control-Allow-Methods "GET, POST, OPTIONS"
   Header always set Access-Control-Allow-Headers "Content-Type, Authorization"
   ```

### Debug Mode

Enable debug logging temporarily:

```apache
# In OAuth provider virtual host
LogLevel debug

# In client application virtual host  
LogLevel auth_openidc:trace1
```

**Remember to disable debug logging in production!**

## Performance Optimization

### Apache MPM Configuration

Edit `/etc/apache2/mods-enabled/mpm_prefork.conf`:

```apache
<IfModule mpm_prefork_module>
    StartServers             5
    MinSpareServers          5
    MaxSpareServers         10
    MaxRequestWorkers      150
    MaxConnectionsPerChild   0
</IfModule>
```

### Enable Compression

```bash
sudo a2enmod deflate

# Add to virtual host
<Location />
    SetOutputFilter DEFLATE
    SetEnvIfNoCase Request_URI \
        \.(?:gif|jpe?g|png)$ no-gzip dont-vary
    SetEnvIfNoCase Request_URI \
        \.(?:exe|t?gz|zip|bz2|sit|rar)$ no-gzip dont-vary
</Location>
```

This configuration guide is based on a working production setup and provides a solid foundation for deploying your OAuth2/OIDC provider with Apache.