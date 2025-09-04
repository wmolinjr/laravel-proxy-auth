<?php

namespace App\Services;

use App\Models\Admin\OAuthMetric;
use App\Models\Admin\AuditLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class OAuthAlertService
{
    /**
     * Alert thresholds configuration
     */
    private const THRESHOLDS = [
        'response_time' => [
            'warning' => 500,  // ms
            'critical' => 1000, // ms
        ],
        'error_rate' => [
            'warning' => 5.0,   // %
            'critical' => 10.0, // %
        ],
        'failed_authentications' => [
            'warning' => 10,    // per hour
            'critical' => 25,   // per hour
        ],
        'requests_per_minute' => [
            'warning' => 500,
            'critical' => 1000,
        ],
    ];

    /**
     * Check all OAuth metrics and trigger alerts if needed
     */
    public static function checkMetrics(): array
    {
        $alerts = [];

        // Check response times
        $alerts = array_merge($alerts, self::checkResponseTimes());
        
        // Check error rates
        $alerts = array_merge($alerts, self::checkErrorRates());
        
        // Check failed authentications
        $alerts = array_merge($alerts, self::checkFailedAuthentications());
        
        // Check request volume
        $alerts = array_merge($alerts, self::checkRequestVolume());
        
        // Check suspicious activity
        $alerts = array_merge($alerts, self::checkSuspiciousActivity());

        // Send alerts if any
        if (!empty($alerts)) {
            self::sendAlerts($alerts);
        }

        return $alerts;
    }

    /**
     * Check response times for all endpoints
     */
    private static function checkResponseTimes(): array
    {
        $alerts = [];
        $since = now()->subMinutes(10);
        
        $endpoints = OAuthMetric::where('created_at', '>=', $since)
            ->select('endpoint')
            ->selectRaw('AVG(response_time_ms) as avg_response_time')
            ->selectRaw('COUNT(*) as request_count')
            ->groupBy('endpoint')
            ->havingRaw('COUNT(*) > 5') // Minimum requests for meaningful data
            ->get();

        foreach ($endpoints as $endpoint) {
            $avgTime = $endpoint->avg_response_time;
            
            if ($avgTime > self::THRESHOLDS['response_time']['critical']) {
                $alerts[] = [
                    'type' => 'response_time',
                    'severity' => 'critical',
                    'endpoint' => $endpoint->endpoint,
                    'message' => "Critical response time: {$avgTime}ms (threshold: " . self::THRESHOLDS['response_time']['critical'] . "ms)",
                    'value' => $avgTime,
                    'threshold' => self::THRESHOLDS['response_time']['critical'],
                ];
            } elseif ($avgTime > self::THRESHOLDS['response_time']['warning']) {
                $alerts[] = [
                    'type' => 'response_time',
                    'severity' => 'warning',
                    'endpoint' => $endpoint->endpoint,
                    'message' => "High response time: {$avgTime}ms (threshold: " . self::THRESHOLDS['response_time']['warning'] . "ms)",
                    'value' => $avgTime,
                    'threshold' => self::THRESHOLDS['response_time']['warning'],
                ];
            }
        }

        return $alerts;
    }

    /**
     * Check error rates for all endpoints
     */
    private static function checkErrorRates(): array
    {
        $alerts = [];
        $since = now()->subMinutes(10);
        
        $endpoints = OAuthMetric::where('created_at', '>=', $since)
            ->select('endpoint')
            ->selectRaw('COUNT(*) as total_requests')
            ->selectRaw('COUNT(*) FILTER(WHERE status_code >= 400) as error_requests')
            ->groupBy('endpoint')
            ->havingRaw('COUNT(*) > 10') // Minimum requests
            ->get();

        foreach ($endpoints as $endpoint) {
            $errorRate = ($endpoint->error_requests / $endpoint->total_requests) * 100;
            
            if ($errorRate > self::THRESHOLDS['error_rate']['critical']) {
                $alerts[] = [
                    'type' => 'error_rate',
                    'severity' => 'critical',
                    'endpoint' => $endpoint->endpoint,
                    'message' => "Critical error rate: {$errorRate}% (threshold: " . self::THRESHOLDS['error_rate']['critical'] . "%)",
                    'value' => $errorRate,
                    'threshold' => self::THRESHOLDS['error_rate']['critical'],
                ];
            } elseif ($errorRate > self::THRESHOLDS['error_rate']['warning']) {
                $alerts[] = [
                    'type' => 'error_rate',
                    'severity' => 'warning',
                    'endpoint' => $endpoint->endpoint,
                    'message' => "High error rate: {$errorRate}% (threshold: " . self::THRESHOLDS['error_rate']['warning'] . "%)",
                    'value' => $errorRate,
                    'threshold' => self::THRESHOLDS['error_rate']['warning'],
                ];
            }
        }

        return $alerts;
    }

    /**
     * Check for unusual number of failed authentications
     */
    private static function checkFailedAuthentications(): array
    {
        $alerts = [];
        $since = now()->subHour();
        
        $failedAuthCount = AuditLog::where('created_at', '>=', $since)
            ->where('event_type', 'oauth_client_authentication_failed')
            ->count();

        if ($failedAuthCount > self::THRESHOLDS['failed_authentications']['critical']) {
            $alerts[] = [
                'type' => 'failed_authentications',
                'severity' => 'critical',
                'message' => "Critical: {$failedAuthCount} failed authentications in the last hour (threshold: " . self::THRESHOLDS['failed_authentications']['critical'] . ")",
                'value' => $failedAuthCount,
                'threshold' => self::THRESHOLDS['failed_authentications']['critical'],
            ];
        } elseif ($failedAuthCount > self::THRESHOLDS['failed_authentications']['warning']) {
            $alerts[] = [
                'type' => 'failed_authentications',
                'severity' => 'warning',
                'message' => "Warning: {$failedAuthCount} failed authentications in the last hour (threshold: " . self::THRESHOLDS['failed_authentications']['warning'] . ")",
                'value' => $failedAuthCount,
                'threshold' => self::THRESHOLDS['failed_authentications']['warning'],
            ];
        }

        return $alerts;
    }

    /**
     * Check request volume for potential DDoS
     */
    private static function checkRequestVolume(): array
    {
        $alerts = [];
        $since = now()->subMinute();
        
        $requestCount = OAuthMetric::where('created_at', '>=', $since)->count();

        if ($requestCount > self::THRESHOLDS['requests_per_minute']['critical']) {
            $alerts[] = [
                'type' => 'request_volume',
                'severity' => 'critical',
                'message' => "Critical request volume: {$requestCount} requests/minute (threshold: " . self::THRESHOLDS['requests_per_minute']['critical'] . ")",
                'value' => $requestCount,
                'threshold' => self::THRESHOLDS['requests_per_minute']['critical'],
            ];
        } elseif ($requestCount > self::THRESHOLDS['requests_per_minute']['warning']) {
            $alerts[] = [
                'type' => 'request_volume',
                'severity' => 'warning',
                'message' => "High request volume: {$requestCount} requests/minute (threshold: " . self::THRESHOLDS['requests_per_minute']['warning'] . ")",
                'value' => $requestCount,
                'threshold' => self::THRESHOLDS['requests_per_minute']['warning'],
            ];
        }

        return $alerts;
    }

    /**
     * Check for suspicious activity patterns
     */
    private static function checkSuspiciousActivity(): array
    {
        $alerts = [];
        $since = now()->subHours(2);

        // Check for multiple failed attempts from same IP
        $suspiciousIPs = OAuthMetric::where('created_at', '>=', $since)
            ->where('status_code', '>=', 400)
            ->select('ip_address')
            ->selectRaw('COUNT(*) as failed_count')
            ->groupBy('ip_address')
            ->havingRaw('COUNT(*) > 20')
            ->get();

        foreach ($suspiciousIPs as $ip) {
            $alerts[] = [
                'type' => 'suspicious_activity',
                'severity' => 'warning',
                'message' => "Suspicious activity from IP {$ip->ip_address}: {$ip->failed_count} failed requests in 2 hours",
                'ip_address' => $ip->ip_address,
                'value' => $ip->failed_count,
            ];

            // Log for audit
            OAuthAuditService::logSuspiciousActivity('repeated_failures', [
                'ip_address' => $ip->ip_address,
                'failed_requests' => $ip->failed_count,
                'time_window' => '2 hours',
            ]);
        }

        // Check for unusual client behavior
        $suspiciousClients = OAuthMetric::where('created_at', '>=', $since)
            ->whereNotNull('client_id')
            ->select('client_id')
            ->selectRaw('COUNT(*) as request_count')
            ->selectRaw('COUNT(*) FILTER(WHERE status_code >= 400) as error_count')
            ->groupBy('client_id')
            ->havingRaw('COUNT(*) > 100')
            ->get();

        foreach ($suspiciousClients as $client) {
            $errorRate = ($client->error_count / $client->request_count) * 100;
            
            if ($errorRate > 50) {
                $alerts[] = [
                    'type' => 'suspicious_client',
                    'severity' => 'warning',
                    'message' => "Client {$client->client_id} has high error rate: {$errorRate}% ({$client->error_count}/{$client->request_count})",
                    'client_id' => $client->client_id,
                    'error_rate' => $errorRate,
                ];
            }
        }

        return $alerts;
    }

    /**
     * Send alerts via configured channels
     */
    private static function sendAlerts(array $alerts): void
    {
        foreach ($alerts as $alert) {
            // Prevent alert spam using cache
            $alertKey = "oauth_alert:" . md5(serialize($alert));
            
            if (Cache::has($alertKey)) {
                continue; // Already alerted recently
            }

            // Cache alert for 15 minutes to prevent spam
            Cache::put($alertKey, true, now()->addMinutes(15));

            // Log alert
            Log::channel('oauth-alerts')->warning('OAuth Alert Triggered', $alert);

            // Send to configured channels
            self::sendToChannels($alert);
        }
    }

    /**
     * Send alert to configured channels (email, Slack, etc.)
     */
    private static function sendToChannels(array $alert): void
    {
        // Example implementations - configure based on your needs
        
        // Email alerts for critical issues
        if ($alert['severity'] === 'critical') {
            // dispatch(new SendOAuthAlertEmail($alert));
        }

        // Slack notifications
        // if (config('oauth.alerts.slack_webhook')) {
        //     dispatch(new SendSlackAlert($alert));
        // }

        // Database logging for dashboard
        AuditLog::logEvent(
            eventType: 'oauth_alert_triggered',
            metadata: $alert
        );
    }

    /**
     * Get current system health status
     */
    public static function getHealthStatus(): array
    {
        $since = now()->subMinutes(5);
        
        $metrics = OAuthMetric::where('created_at', '>=', $since);
        
        $totalRequests = $metrics->count();
        $avgResponseTime = $metrics->avg('response_time_ms');
        $errorCount = $metrics->where('status_code', '>=', 400)->count();
        $errorRate = $totalRequests > 0 ? ($errorCount / $totalRequests) * 100 : 0;

        $status = 'healthy';
        $issues = [];

        // Determine overall health status
        if ($avgResponseTime > self::THRESHOLDS['response_time']['critical'] || 
            $errorRate > self::THRESHOLDS['error_rate']['critical']) {
            $status = 'critical';
        } elseif ($avgResponseTime > self::THRESHOLDS['response_time']['warning'] || 
                  $errorRate > self::THRESHOLDS['error_rate']['warning']) {
            $status = 'warning';
        }

        if ($avgResponseTime > self::THRESHOLDS['response_time']['warning']) {
            $issues[] = "High response times: {$avgResponseTime}ms";
        }

        if ($errorRate > self::THRESHOLDS['error_rate']['warning']) {
            $issues[] = "High error rate: {$errorRate}%";
        }

        return [
            'status' => $status,
            'timestamp' => now()->toISOString(),
            'metrics' => [
                'requests_5min' => $totalRequests,
                'avg_response_time' => round($avgResponseTime, 2),
                'error_rate' => round($errorRate, 2),
                'error_count' => $errorCount,
            ],
            'issues' => $issues,
        ];
    }

    /**
     * Manual alert test
     */
    public static function testAlerts(): array
    {
        $testAlert = [
            'type' => 'test',
            'severity' => 'info',
            'message' => 'Test alert triggered manually',
            'timestamp' => now()->toISOString(),
        ];

        self::sendToChannels($testAlert);

        return ['status' => 'sent', 'alert' => $testAlert];
    }
}