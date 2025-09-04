# 📊 Monitoring & Metrics Guide

This guide covers the comprehensive monitoring and metrics system built into the Laravel OAuth2/OIDC Identity Provider.

## Overview

The monitoring system provides real-time insights into:
- **Performance Metrics**: Response times, throughput, error rates
- **Security Events**: Authentication attempts, suspicious activities
- **Usage Analytics**: Client statistics, endpoint usage, user patterns
- **System Health**: Database, cache, queue status
- **Audit Logging**: Complete trail of OAuth events

## Metrics Collection

### Automatic Metrics Collection

Metrics are automatically collected for all OAuth endpoints through the `OAuthMetricsMiddleware`:

```php
// Automatically tracked metrics
- Request count and frequency
- Response times (avg, p95, p99)
- HTTP status codes
- Client identification
- User identification (when available)
- Requested scopes
- Error types and frequencies
- IP addresses and user agents
```

### Metrics Database Schema

```sql
-- OAuth metrics table
CREATE TABLE oauth_metrics (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    endpoint VARCHAR(50) NOT NULL,
    client_id VARCHAR(100) NULL,
    user_id INT NULL,
    response_time_ms INT NOT NULL,
    status_code INT NOT NULL,
    token_type VARCHAR(50) NULL,
    scopes JSON NULL,
    error_type VARCHAR(100) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    metadata JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_endpoint_created (endpoint, created_at),
    INDEX idx_client_created (client_id, created_at),
    INDEX idx_response_time (response_time_ms),
    INDEX idx_status_code (status_code)
);
```

## Health Monitoring

### Built-in Health Checks

#### System Health Command
```bash
# Check overall system health
php artisan oauth:health-check

# Send test alerts
php artisan oauth:health-check --test

# Check specific components
php artisan oauth:health-check --component=database
php artisan oauth:health-check --component=redis
php artisan oauth:health-check --component=oauth
```

#### Health Check Response
```json
{
    "status": "healthy",
    "timestamp": "2024-01-15T10:30:00Z",
    "uptime": "15 days, 4 hours, 32 minutes",
    "checks": {
        "database": {
            "status": "healthy",
            "response_time": 12,
            "details": "Connected to MySQL 8.0"
        },
        "redis": {
            "status": "healthy", 
            "response_time": 3,
            "details": "Connected to Redis 7.0"
        },
        "oauth": {
            "status": "healthy",
            "metrics": {
                "requests_5min": 245,
                "avg_response_time": 89,
                "error_rate": 1.2,
                "active_tokens": 1834
            }
        },
        "queue": {
            "status": "healthy",
            "pending_jobs": 12,
            "failed_jobs": 0,
            "workers": 3
        }
    },
    "alerts": []
}
```

### Scheduled Health Monitoring

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Run health checks every 5 minutes
    $schedule->command('oauth:health-check')
             ->everyFiveMinutes()
             ->onFailure(function () {
                 // Send alert on failure
                 Notification::route('mail', 'admin@example.com')
                     ->notify(new HealthCheckFailedNotification());
             });
    
    // Cleanup old metrics daily
    $schedule->command('oauth:cleanup-metrics')
             ->daily()
             ->at('02:00');
    
    // Generate daily reports
    $schedule->command('oauth:daily-report')
             ->dailyAt('06:00');
}
```

## Metrics Dashboard

### Access Dashboard
```
https://your-oauth-provider.com/admin/oauth-metrics
```

### Dashboard Features

#### Real-time Metrics
- Live request counter
- Current response time
- Active user sessions
- Error rate gauge

#### Historical Analytics
- Request volume trends
- Performance over time
- Error rate patterns
- Client usage statistics

#### Performance Insights
```php
// Example dashboard data structure
[
    'overview' => [
        'total_requests_today' => 12543,
        'avg_response_time' => 94, // milliseconds
        'error_rate' => 2.1, // percentage
        'active_clients' => 23,
        'active_users' => 1847,
    ],
    
    'trends' => [
        'hourly_requests' => [
            '00:00' => 234,
            '01:00' => 189,
            // ... 24 hours of data
        ],
        'response_times' => [
            'avg' => [89, 94, 87, 91], // Last 4 hours
            'p95' => [145, 152, 139, 148],
            'p99' => [289, 301, 267, 294],
        ],
    ],
    
    'clients' => [
        [
            'client_id' => 'web-app',
            'name' => 'Web Application',
            'requests_today' => 5643,
            'error_rate' => 1.2,
            'avg_response_time' => 87,
        ],
        // ... other clients
    ],
    
    'endpoints' => [
        'token' => ['requests' => 4521, 'avg_time' => 156],
        'authorize' => ['requests' => 4521, 'avg_time' => 45],
        'userinfo' => ['requests' => 3201, 'avg_time' => 67],
        'introspect' => ['requests' => 890, 'avg_time' => 34],
    ],
]
```

### Custom Dashboard Queries

```php
use App\Models\Admin\OAuthMetric;
use Carbon\Carbon;

class MetricsDashboardController extends Controller 
{
    public function getPerformanceMetrics(Request $request)
    {
        $timeRange = $request->get('range', '24h');
        $startTime = match($timeRange) {
            '1h' => Carbon::now()->subHour(),
            '24h' => Carbon::now()->subDay(),
            '7d' => Carbon::now()->subWeek(),
            '30d' => Carbon::now()->subMonth(),
            default => Carbon::now()->subDay(),
        };

        return [
            'response_times' => OAuthMetric::selectRaw('
                DATE_FORMAT(created_at, "%Y-%m-%d %H:00:00") as hour,
                AVG(response_time_ms) as avg_time,
                PERCENTILE_CONT(0.95) WITHIN GROUP (ORDER BY response_time_ms) as p95_time,
                PERCENTILE_CONT(0.99) WITHIN GROUP (ORDER BY response_time_ms) as p99_time
            ')
            ->where('created_at', '>=', $startTime)
            ->groupBy('hour')
            ->orderBy('hour')
            ->get(),
            
            'error_rates' => OAuthMetric::selectRaw('
                DATE_FORMAT(created_at, "%Y-%m-%d %H:00:00") as hour,
                COUNT(*) as total_requests,
                SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) as error_count,
                (SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) * 100.0 / COUNT(*)) as error_rate
            ')
            ->where('created_at', '>=', $startTime)
            ->groupBy('hour')
            ->orderBy('hour')
            ->get(),
            
            'top_clients' => OAuthMetric::selectRaw('
                client_id,
                COUNT(*) as request_count,
                AVG(response_time_ms) as avg_response_time,
                (SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) * 100.0 / COUNT(*)) as error_rate
            ')
            ->whereNotNull('client_id')
            ->where('created_at', '>=', $startTime)
            ->groupBy('client_id')
            ->orderBy('request_count', 'desc')
            ->limit(10)
            ->get(),
        ];
    }
}
```

## Alert System

### Alert Configuration

```php
// config/oauth.php
'alerts' => [
    'enabled' => true,
    'channels' => ['mail', 'slack', 'webhook'],
    
    'thresholds' => [
        'response_time' => [
            'warning' => 500,   // milliseconds
            'critical' => 1000,
        ],
        'error_rate' => [
            'warning' => 5.0,   // percentage
            'critical' => 10.0,
        ],
        'failed_authentications' => [
            'warning' => 10,    // count per 5 minutes
            'critical' => 25,
        ],
        'queue_size' => [
            'warning' => 100,   // pending jobs
            'critical' => 500,
        ],
        'database_connections' => [
            'warning' => 80,    // percentage of pool
            'critical' => 95,
        ],
    ],
    
    'cooldown' => [
        'warning' => 300,    // 5 minutes
        'critical' => 600,   // 10 minutes
    ],
    
    'notification_channels' => [
        'mail' => [
            'to' => ['admin@example.com', 'ops@example.com'],
            'subject_prefix' => '[OAuth Alert]',
        ],
        'slack' => [
            'webhook_url' => env('SLACK_WEBHOOK_URL'),
            'channel' => '#oauth-alerts',
            'username' => 'OAuth Monitor',
        ],
        'webhook' => [
            'url' => env('ALERT_WEBHOOK_URL'),
            'method' => 'POST',
            'headers' => [
                'Authorization' => 'Bearer ' . env('ALERT_WEBHOOK_TOKEN'),
            ],
        ],
    ],
],
```

### Alert Types

#### Performance Alerts
```php
// High response time alert
[
    'type' => 'performance',
    'severity' => 'warning',
    'metric' => 'response_time',
    'current_value' => 750,
    'threshold' => 500,
    'endpoint' => 'oauth/token',
    'message' => 'OAuth token endpoint response time exceeded threshold',
    'timestamp' => '2024-01-15T10:30:00Z',
]

// High error rate alert  
[
    'type' => 'performance',
    'severity' => 'critical',
    'metric' => 'error_rate',
    'current_value' => 12.5,
    'threshold' => 10.0,
    'time_window' => '5 minutes',
    'message' => 'OAuth error rate critically high',
]
```

#### Security Alerts
```php
// Multiple failed authentication attempts
[
    'type' => 'security',
    'severity' => 'warning',
    'event' => 'multiple_failed_auth',
    'ip_address' => '192.168.1.100',
    'attempts' => 8,
    'time_window' => '5 minutes',
    'message' => 'Multiple failed authentication attempts from single IP',
]

// Suspicious client activity
[
    'type' => 'security', 
    'severity' => 'critical',
    'event' => 'rate_limit_exceeded',
    'client_id' => 'suspicious-client',
    'requests' => 1000,
    'time_window' => '1 minute',
    'message' => 'Client exceeded rate limits significantly',
]
```

### Custom Alert Rules

```php
use App\Services\OAuthAlertService;

class CustomMetricsAlert
{
    public function handle()
    {
        // Custom business logic alert
        $activeTokens = OAuthAccessToken::where('expires_at', '>', now())->count();
        $threshold = 10000;
        
        if ($activeTokens > $threshold) {
            OAuthAlertService::sendAlert([
                'type' => 'capacity',
                'severity' => 'warning',
                'metric' => 'active_tokens',
                'current_value' => $activeTokens,
                'threshold' => $threshold,
                'message' => "High number of active tokens: {$activeTokens}",
                'recommendations' => [
                    'Consider reducing token lifetime',
                    'Review client token usage patterns',
                    'Monitor for potential token hoarding',
                ],
            ]);
        }
    }
}
```

## Audit Logging

### Audit Events

All critical OAuth events are automatically logged:

```php
// Authentication events
OAuthAuditService::logAuthorizationGranted($userId, $clientId, $scopes);
OAuthAuditService::logTokenIssued($clientId, $userId, $grantType, $scopes);
OAuthAuditService::logTokenRefreshed($refreshTokenId, $clientId);
OAuthAuditService::logTokenRevoked($tokenId, $reason);

// Security events  
OAuthAuditService::logFailedAuthentication($clientId, $error, $ipAddress);
OAuthAuditService::logSuspiciousActivity($event, $details, $ipAddress);
OAuthAuditService::logRateLimitExceeded($clientId, $endpoint, $ipAddress);

// Administrative events
OAuthAuditService::logClientCreated($clientId, $adminUserId);
OAuthAuditService::logClientUpdated($clientId, $changes, $adminUserId);
OAuthAuditService::logClientDeleted($clientId, $adminUserId);
```

### Audit Log Format

```json
{
    "timestamp": "2024-01-15T10:30:45Z",
    "event_type": "token_issued",
    "severity": "info",
    "client_id": "web-app-client",
    "user_id": 123,
    "session_id": "sess_abc123",
    "ip_address": "192.168.1.100",
    "user_agent": "Mozilla/5.0...",
    "details": {
        "grant_type": "authorization_code",
        "scopes": ["openid", "profile", "email"],
        "token_id": "token_xyz789",
        "expires_at": "2024-01-15T11:30:45Z"
    },
    "metadata": {
        "request_id": "req_def456",
        "response_time_ms": 89,
        "endpoint": "oauth/token"
    }
}
```

### Compliance Reporting

Generate compliance reports for auditing:

```bash
# Generate OAuth activity report
php artisan oauth:audit-report --start=2024-01-01 --end=2024-01-31

# Export security events
php artisan oauth:security-report --format=csv --output=security-events.csv

# Generate client usage report
php artisan oauth:client-report --client=web-app-client --days=30
```

## Performance Monitoring

### Database Performance

```php
// Monitor slow OAuth queries
use Illuminate\Support\Facades\DB;

DB::listen(function ($query) {
    if ($query->time > 100) { // Log queries over 100ms
        Log::channel('oauth')->warning('Slow OAuth query detected', [
            'sql' => $query->sql,
            'bindings' => $query->bindings,
            'time' => $query->time,
        ]);
    }
});
```

### Queue Monitoring

```bash
# Monitor queue status
php artisan queue:monitor

# Check failed jobs
php artisan queue:failed

# Queue statistics
php artisan oauth:queue-stats
```

### Redis Monitoring

```php
use Illuminate\Support\Facades\Redis;

// Monitor Redis performance
$info = Redis::info();
$memoryUsage = $info['used_memory_human'];
$hitRate = $info['keyspace_hits'] / ($info['keyspace_hits'] + $info['keyspace_misses']);

Log::channel('oauth_metrics')->info('Redis performance', [
    'memory_usage' => $memoryUsage,
    'hit_rate' => $hitRate,
    'connected_clients' => $info['connected_clients'],
]);
```

## External Monitoring Integration

### Prometheus Metrics

```php
// Export metrics for Prometheus
Route::get('/metrics', function () {
    $metrics = [
        '# HELP oauth_requests_total Total OAuth requests',
        '# TYPE oauth_requests_total counter',
    ];
    
    $endpoints = ['token', 'authorize', 'userinfo', 'introspect'];
    
    foreach ($endpoints as $endpoint) {
        $count = OAuthMetric::where('endpoint', $endpoint)
                          ->where('created_at', '>=', now()->subDay())
                          ->count();
        
        $metrics[] = "oauth_requests_total{endpoint=\"{$endpoint}\"} {$count}";
    }
    
    return response(implode("\n", $metrics))
           ->header('Content-Type', 'text/plain');
});
```

### DataDog Integration

```php
use DataDog\DogStatsd;

$statsd = new DogStatsd();

// Send custom metrics to DataDog
$statsd->increment('oauth.token.requests', 1, [
    'client_id' => $clientId,
    'grant_type' => $grantType,
]);

$statsd->timing('oauth.token.response_time', $responseTime, [
    'endpoint' => 'token',
]);

$statsd->gauge('oauth.active_tokens', $activeTokenCount);
```

### New Relic Integration

```php
if (extension_loaded('newrelic')) {
    // Set custom attributes
    newrelic_add_custom_parameter('oauth.client_id', $clientId);
    newrelic_add_custom_parameter('oauth.endpoint', $endpoint);
    newrelic_add_custom_parameter('oauth.response_time', $responseTime);
    
    // Record custom events
    newrelic_record_custom_event('OAuthTokenIssued', [
        'client_id' => $clientId,
        'grant_type' => $grantType,
        'scopes' => implode(',', $scopes),
    ]);
}
```

## Troubleshooting with Metrics

### Common Issues and Diagnostics

#### High Response Times
```sql
-- Find slowest endpoints
SELECT 
    endpoint,
    AVG(response_time_ms) as avg_time,
    PERCENTILE_CONT(0.95) WITHIN GROUP (ORDER BY response_time_ms) as p95_time,
    COUNT(*) as request_count
FROM oauth_metrics 
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
GROUP BY endpoint
ORDER BY avg_time DESC;
```

#### Error Pattern Analysis
```sql
-- Analyze error patterns
SELECT 
    endpoint,
    status_code,
    error_type,
    COUNT(*) as error_count,
    COUNT(*) * 100.0 / SUM(COUNT(*)) OVER() as error_percentage
FROM oauth_metrics 
WHERE status_code >= 400 
    AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
GROUP BY endpoint, status_code, error_type
ORDER BY error_count DESC;
```

#### Client Usage Analysis
```sql
-- Top clients by request volume
SELECT 
    client_id,
    COUNT(*) as requests,
    AVG(response_time_ms) as avg_response_time,
    SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) as errors
FROM oauth_metrics 
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
    AND client_id IS NOT NULL
GROUP BY client_id
ORDER BY requests DESC
LIMIT 20;
```

### Performance Optimization

Based on metrics, optimize performance:

1. **Database Optimization**
   ```sql
   -- Add indexes for slow queries
   CREATE INDEX idx_metrics_endpoint_time ON oauth_metrics(endpoint, created_at, response_time_ms);
   CREATE INDEX idx_metrics_client_time ON oauth_metrics(client_id, created_at);
   ```

2. **Caching Optimization**
   ```php
   // Cache frequently accessed data
   Cache::remember("client_{$clientId}", 1800, function () use ($clientId) {
       return OAuthClient::find($clientId);
   });
   ```

3. **Queue Optimization**
   ```bash
   # Scale queue workers based on metrics load
   php artisan queue:work --queue=metrics --processes=4
   ```

## Metrics Retention

### Automatic Cleanup

```php
// app/Console/Commands/CleanupMetrics.php
class CleanupMetrics extends Command
{
    protected $signature = 'oauth:cleanup-metrics {--days=30}';
    
    public function handle()
    {
        $days = $this->option('days');
        $cutoff = Carbon::now()->subDays($days);
        
        $deleted = OAuthMetric::where('created_at', '<', $cutoff)->delete();
        
        $this->info("Deleted {$deleted} metric records older than {$days} days");
    }
}
```

### Data Archival

```php
// Archive old metrics to long-term storage
class ArchiveMetrics extends Command
{
    public function handle()
    {
        $cutoff = Carbon::now()->subDays(90);
        
        // Export to CSV/JSON for long-term storage
        $metrics = OAuthMetric::where('created_at', '<', $cutoff)->get();
        
        Storage::disk('archives')->put(
            "oauth-metrics-{$cutoff->format('Y-m')}.json",
            $metrics->toJson()
        );
        
        // Delete from active database
        OAuthMetric::where('created_at', '<', $cutoff)->delete();
    }
}
```

## Next Steps

- [🛡️ Configure security settings](security.md)
- [🚀 Prepare for deployment](deployment.md)
- [🔌 Review API documentation](api-reference.md)
- [❓ Check troubleshooting guide](troubleshooting.md)