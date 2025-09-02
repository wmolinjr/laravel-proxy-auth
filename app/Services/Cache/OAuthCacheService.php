<?php

namespace App\Services\Cache;

use App\Models\OAuth\OAuthClient;
use App\Models\OAuth\OAuthClientUsage;
use App\Models\OAuthNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;

class OAuthCacheService
{
    private const CACHE_TTL = 300; // 5 minutes
    private const STATS_CACHE_TTL = 60; // 1 minute for frequently changing stats
    private const LONG_CACHE_TTL = 3600; // 1 hour for stable data

    /**
     * Cache dashboard statistics
     */
    public function getDashboardStats(): array
    {
        return Cache::remember('oauth.dashboard.stats', self::STATS_CACHE_TTL, function () {
            $clientStats = $this->calculateClientStats();
            $notificationStats = $this->calculateNotificationStats();
            $systemPerformance = $this->calculateSystemPerformance();
            
            return [
                'oauth_clients' => $clientStats,
                'notifications' => $notificationStats,
                'system_performance' => $systemPerformance,
                'last_updated' => now()->toISOString(),
            ];
        });
    }

    /**
     * Cache client overview data
     */
    public function getClientsOverview(): Collection
    {
        return Cache::remember('oauth.clients.overview', self::CACHE_TTL, function () {
            return OAuthClient::with(['usageStats' => function ($query) {
                $query->where('date', now()->toDateString());
            }])
            ->select([
                'id', 'name', 'health_status', 'environment', 
                'maintenance_mode', 'is_active', 'last_health_check'
            ])
            ->orderBy('name')
            ->get()
            ->map(function ($client) {
                $todayUsage = $client->usageStats->first();
                
                return [
                    'id' => $client->id,
                    'name' => $client->name,
                    'health_status' => $client->health_status,
                    'environment' => $client->environment,
                    'maintenance_mode' => $client->maintenance_mode,
                    'is_active' => $client->is_active,
                    'last_health_check' => $client->last_health_check,
                    'today_requests' => $todayUsage?->total_requests ?? 0,
                    'today_success_rate' => $todayUsage ? 
                        round(($todayUsage->successful_requests / $todayUsage->total_requests) * 100, 2) : 
                        0,
                ];
            });
        });
    }

    /**
     * Cache recent notifications
     */
    public function getRecentNotifications(int $limit = 10): Collection
    {
        $cacheKey = "oauth.notifications.recent.{$limit}";
        
        return Cache::remember($cacheKey, self::STATS_CACHE_TTL, function () use ($limit) {
            return OAuthNotification::with('oauthClient:id,name')
                ->where('created_at', '>=', now()->subHours(24))
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($notification) {
                    return [
                        'id' => $notification->id,
                        'type' => $notification->type,
                        'title' => $notification->title,
                        'message' => $notification->message,
                        'created_at' => $notification->created_at,
                        'acknowledged_at' => $notification->acknowledged_at,
                        'oauth_client' => [
                            'id' => $notification->oauthClient?->id,
                            'name' => $notification->oauthClient?->name,
                        ],
                    ];
                });
        });
    }

    /**
     * Cache client analytics data
     */
    public function getClientAnalytics(string $clientId, array $dateRange = null): array
    {
        $cacheKey = "oauth.client.{$clientId}.analytics." . md5(json_encode($dateRange));
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($clientId, $dateRange) {
            $query = OAuthClientUsage::where('client_id', $clientId);
            
            if ($dateRange) {
                $query->whereBetween('date', [$dateRange['from'], $dateRange['to']]);
            } else {
                // Default to last 30 days
                $query->where('date', '>=', now()->subDays(30)->toDateString());
            }
            
            $usageData = $query->orderBy('date', 'desc')->get();
            
            return [
                'summary' => $this->calculateUsageSummary($usageData),
                'daily_trend' => $this->formatDailyTrend($usageData),
                'error_breakdown' => $this->calculateErrorBreakdown($usageData),
            ];
        });
    }

    /**
     * Cache health check status for multiple clients
     */
    public function getHealthStatuses(): array
    {
        return Cache::remember('oauth.health.statuses', self::STATS_CACHE_TTL, function () {
            return OAuthClient::where('health_check_enabled', true)
                ->pluck('health_status', 'id')
                ->toArray();
        });
    }

    /**
     * Cache notification center data
     */
    public function getNotificationCenterData(): array
    {
        return Cache::remember('oauth.notification_center', 30, function () { // 30 seconds for real-time feel
            $notifications = OAuthNotification::with('oauthClient:id,name')
                ->whereNull('acknowledged_at')
                ->where('created_at', '>=', now()->subHours(24))
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get();
            
            return [
                'notifications' => $notifications->map(function ($notification) {
                    return [
                        'id' => $notification->id,
                        'type' => $notification->type,
                        'title' => $notification->title,
                        'message' => $notification->message,
                        'created_at' => $notification->created_at,
                        'oauth_client' => [
                            'id' => $notification->oauthClient?->id,
                            'name' => $notification->oauthClient?->name,
                        ],
                    ];
                }),
                'unread_count' => $notifications->count(),
            ];
        });
    }

    /**
     * Invalidate dashboard caches
     */
    public function invalidateDashboardCache(): void
    {
        Cache::forget('oauth.dashboard.stats');
        Cache::forget('oauth.clients.overview');
        Cache::forget('oauth.notification_center');
        
        // Clear recent notifications cache
        for ($i = 1; $i <= 50; $i++) {
            Cache::forget("oauth.notifications.recent.{$i}");
        }
    }

    /**
     * Invalidate client-specific caches
     */
    public function invalidateClientCache(string $clientId): void
    {
        // Remove all analytics caches for this client
        $pattern = "oauth.client.{$clientId}.analytics.*";
        $this->forgetCachePattern($pattern);
        
        // Invalidate dashboard caches as they might include this client
        $this->invalidateDashboardCache();
    }

    /**
     * Invalidate notification caches
     */
    public function invalidateNotificationCache(): void
    {
        Cache::forget('oauth.notification_center');
        
        // Clear recent notifications cache
        for ($i = 1; $i <= 50; $i++) {
            Cache::forget("oauth.notifications.recent.{$i}");
        }
        
        // Update dashboard stats
        Cache::forget('oauth.dashboard.stats');
    }

    /**
     * Calculate client statistics
     */
    private function calculateClientStats(): array
    {
        $clients = OAuthClient::select(['health_status', 'maintenance_mode', 'is_active'])->get();
        
        return [
            'total' => $clients->count(),
            'active' => $clients->where('is_active', true)->count(),
            'healthy' => $clients->where('health_status', 'healthy')->count(),
            'unhealthy' => $clients->where('health_status', 'unhealthy')->count(),
            'error' => $clients->where('health_status', 'error')->count(),
            'unknown' => $clients->where('health_status', 'unknown')->count(),
            'maintenance' => $clients->where('maintenance_mode', true)->count(),
        ];
    }

    /**
     * Calculate notification statistics
     */
    private function calculateNotificationStats(): array
    {
        $today = now()->toDateString();
        $notifications = OAuthNotification::select(['type', 'acknowledged_at', 'created_at'])
            ->get();
        
        $unacknowledged = $notifications->whereNull('acknowledged_at');
        $todayNotifications = $notifications->where('created_at', '>=', $today);
        
        return [
            'total' => $notifications->count(),
            'unacknowledged' => $unacknowledged->count(),
            'critical' => $unacknowledged->where('type', 'critical')->count(),
            'alert' => $unacknowledged->where('type', 'alert')->count(),
            'warning' => $unacknowledged->where('type', 'warning')->count(),
            'info' => $unacknowledged->where('type', 'info')->count(),
            'today' => $todayNotifications->count(),
        ];
    }

    /**
     * Calculate system performance metrics
     */
    private function calculateSystemPerformance(): array
    {
        $todayUsage = OAuthClientUsage::where('date', now()->toDateString())->get();
        
        $totalRequests = $todayUsage->sum('total_requests');
        $successfulRequests = $todayUsage->sum('successful_requests');
        $totalResponseTime = $todayUsage->sum(function ($usage) {
            return $usage->avg_response_time * $usage->total_requests;
        });
        
        return [
            'requests_today' => $totalRequests,
            'success_rate' => $totalRequests > 0 ? 
                round(($successfulRequests / $totalRequests) * 100, 2) : 0,
            'avg_response_time' => $totalRequests > 0 ? 
                round($totalResponseTime / $totalRequests, 2) : 0,
            'system_uptime' => $this->calculateSystemUptime(),
        ];
    }

    /**
     * Calculate usage summary
     */
    private function calculateUsageSummary(Collection $usageData): array
    {
        $totalRequests = $usageData->sum('total_requests');
        $successfulRequests = $usageData->sum('successful_requests');
        $failedRequests = $usageData->sum('failed_requests');
        
        return [
            'total_requests' => $totalRequests,
            'successful_requests' => $successfulRequests,
            'failed_requests' => $failedRequests,
            'success_rate' => $totalRequests > 0 ? 
                round(($successfulRequests / $totalRequests) * 100, 2) : 0,
            'unique_users' => $usageData->sum('unique_users'),
            'avg_response_time' => $usageData->avg('avg_response_time') ?? 0,
        ];
    }

    /**
     * Format daily trend data
     */
    private function formatDailyTrend(Collection $usageData): array
    {
        return $usageData->map(function ($usage) {
            return [
                'date' => $usage->date,
                'requests' => $usage->total_requests,
                'success_rate' => $usage->total_requests > 0 ? 
                    round(($usage->successful_requests / $usage->total_requests) * 100, 2) : 0,
                'users' => $usage->unique_users,
                'response_time' => $usage->avg_response_time,
            ];
        })->toArray();
    }

    /**
     * Calculate error breakdown
     */
    private function calculateErrorBreakdown(Collection $usageData): array
    {
        // This would be expanded based on actual error tracking implementation
        $totalFailed = $usageData->sum('failed_requests');
        
        return [
            'total_errors' => $totalFailed,
            'categories' => [
                'authentication_failed' => round($totalFailed * 0.4), // Example breakdown
                'rate_limit_exceeded' => round($totalFailed * 0.3),
                'server_error' => round($totalFailed * 0.2),
                'timeout' => round($totalFailed * 0.1),
            ],
        ];
    }

    /**
     * Calculate system uptime percentage
     */
    private function calculateSystemUptime(): float
    {
        // Simple implementation - could be enhanced with actual uptime tracking
        $healthyClients = OAuthClient::where('health_status', 'healthy')
            ->where('health_check_enabled', true)
            ->count();
        
        $totalMonitoredClients = OAuthClient::where('health_check_enabled', true)->count();
        
        return $totalMonitoredClients > 0 ? 
            round(($healthyClients / $totalMonitoredClients) * 100, 2) : 100.0;
    }

    /**
     * Helper to forget cache keys matching a pattern
     */
    private function forgetCachePattern(string $pattern): void
    {
        // This is a simplified implementation
        // In production, you might want to use Redis KEYS command or tag-based cache clearing
        $keys = Cache::getRedis()->keys($pattern);
        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }
}