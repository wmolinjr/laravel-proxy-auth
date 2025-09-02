<?php

namespace App\Services\Performance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class OAuthPerformanceMonitoringService
{
    private const SLOW_QUERY_THRESHOLD = 1000; // milliseconds
    private const CACHE_HIT_TARGET = 80; // percentage

    /**
     * Monitor and log slow database queries
     */
    public function monitorDatabasePerformance(): array
    {
        $metrics = [];

        // Enable query logging temporarily
        DB::enableQueryLog();
        
        // Perform sample queries to measure performance
        $startTime = microtime(true);
        
        // Sample dashboard queries
        $this->runSampleDashboardQueries();
        
        $endTime = microtime(true);
        $totalTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $slowQueries = collect($queries)->filter(function ($query) {
            return $query['time'] > self::SLOW_QUERY_THRESHOLD;
        });

        $metrics = [
            'total_queries' => count($queries),
            'total_time_ms' => round($totalTime, 2),
            'average_query_time_ms' => count($queries) > 0 ? round($totalTime / count($queries), 2) : 0,
            'slow_queries_count' => $slowQueries->count(),
            'slow_queries' => $slowQueries->map(function ($query) {
                return [
                    'query' => $query['query'],
                    'time_ms' => $query['time'],
                    'bindings' => $query['bindings'],
                ];
            })->toArray(),
        ];

        // Log slow queries for monitoring
        if ($slowQueries->isNotEmpty()) {
            Log::warning('Slow OAuth queries detected', [
                'slow_queries_count' => $slowQueries->count(),
                'queries' => $slowQueries->pluck('query')->toArray(),
            ]);
        }

        return $metrics;
    }

    /**
     * Monitor cache performance
     */
    public function monitorCachePerformance(): array
    {
        $cacheKeys = [
            'oauth.dashboard.stats',
            'oauth.clients.overview',
            'oauth.notification_center',
            'oauth.health.statuses',
        ];

        $hitCount = 0;
        $missCount = 0;
        $keyStatus = [];

        foreach ($cacheKeys as $key) {
            $exists = Cache::has($key);
            $keyStatus[$key] = $exists ? 'hit' : 'miss';
            
            if ($exists) {
                $hitCount++;
            } else {
                $missCount++;
            }
        }

        $totalChecks = $hitCount + $missCount;
        $hitRatePercent = $totalChecks > 0 ? round(($hitCount / $totalChecks) * 100, 2) : 0;

        $metrics = [
            'cache_hits' => $hitCount,
            'cache_misses' => $missCount,
            'hit_rate_percent' => $hitRatePercent,
            'target_hit_rate_percent' => self::CACHE_HIT_TARGET,
            'meets_target' => $hitRatePercent >= self::CACHE_HIT_TARGET,
            'key_status' => $keyStatus,
        ];

        // Log cache performance issues
        if ($hitRatePercent < self::CACHE_HIT_TARGET) {
            Log::info('OAuth cache hit rate below target', [
                'current_hit_rate' => $hitRatePercent,
                'target_hit_rate' => self::CACHE_HIT_TARGET,
                'missed_keys' => array_keys(array_filter($keyStatus, fn($status) => $status === 'miss')),
            ]);
        }

        return $metrics;
    }

    /**
     * Monitor memory usage
     */
    public function monitorMemoryUsage(): array
    {
        $memoryUsage = memory_get_usage(true);
        $memoryPeak = memory_get_peak_usage(true);
        $memoryLimit = ini_get('memory_limit');

        // Convert memory limit to bytes
        $memoryLimitBytes = $this->convertToBytes($memoryLimit);

        return [
            'current_usage_mb' => round($memoryUsage / 1024 / 1024, 2),
            'peak_usage_mb' => round($memoryPeak / 1024 / 1024, 2),
            'memory_limit' => $memoryLimit,
            'memory_limit_mb' => round($memoryLimitBytes / 1024 / 1024, 2),
            'usage_percentage' => round(($memoryUsage / $memoryLimitBytes) * 100, 2),
            'peak_percentage' => round(($memoryPeak / $memoryLimitBytes) * 100, 2),
        ];
    }

    /**
     * Get comprehensive performance report
     */
    public function getPerformanceReport(): array
    {
        return [
            'timestamp' => now()->toISOString(),
            'database_performance' => $this->monitorDatabasePerformance(),
            'cache_performance' => $this->monitorCachePerformance(),
            'memory_usage' => $this->monitorMemoryUsage(),
            'recommendations' => $this->generatePerformanceRecommendations(),
        ];
    }

    /**
     * Generate performance optimization recommendations
     */
    public function generatePerformanceRecommendations(): array
    {
        $recommendations = [];

        // Database recommendations
        $dbMetrics = $this->monitorDatabasePerformance();
        if ($dbMetrics['slow_queries_count'] > 0) {
            $recommendations[] = [
                'type' => 'database',
                'priority' => 'high',
                'title' => 'Slow Queries Detected',
                'description' => "Found {$dbMetrics['slow_queries_count']} slow queries. Consider adding indexes or optimizing query structure.",
                'action' => 'Review and optimize slow queries listed in the performance report.',
            ];
        }

        if ($dbMetrics['average_query_time_ms'] > 500) {
            $recommendations[] = [
                'type' => 'database',
                'priority' => 'medium',
                'title' => 'High Average Query Time',
                'description' => "Average query time is {$dbMetrics['average_query_time_ms']}ms. Target is under 100ms.",
                'action' => 'Consider implementing query optimization and database indexing.',
            ];
        }

        // Cache recommendations
        $cacheMetrics = $this->monitorCachePerformance();
        if (!$cacheMetrics['meets_target']) {
            $recommendations[] = [
                'type' => 'cache',
                'priority' => 'medium',
                'title' => 'Low Cache Hit Rate',
                'description' => "Cache hit rate is {$cacheMetrics['hit_rate_percent']}%. Target is {$cacheMetrics['target_hit_rate_percent']}%.",
                'action' => 'Review cache warming strategies and increase cache TTL for stable data.',
            ];
        }

        // Memory recommendations
        $memoryMetrics = $this->monitorMemoryUsage();
        if ($memoryMetrics['usage_percentage'] > 80) {
            $recommendations[] = [
                'type' => 'memory',
                'priority' => 'high',
                'title' => 'High Memory Usage',
                'description' => "Memory usage is {$memoryMetrics['usage_percentage']}% of available memory.",
                'action' => 'Investigate memory leaks and consider increasing memory allocation.',
            ];
        }

        return $recommendations;
    }

    /**
     * Log performance metrics for monitoring
     */
    public function logPerformanceMetrics(): void
    {
        $report = $this->getPerformanceReport();
        
        Log::info('OAuth Performance Metrics', [
            'database_avg_query_time' => $report['database_performance']['average_query_time_ms'],
            'database_slow_queries' => $report['database_performance']['slow_queries_count'],
            'cache_hit_rate' => $report['cache_performance']['hit_rate_percent'],
            'memory_usage_percent' => $report['memory_usage']['usage_percentage'],
            'recommendations_count' => count($report['recommendations']),
        ]);

        // Store metrics in cache for dashboard display
        Cache::put('oauth.performance_metrics', $report, 300); // 5 minutes
    }

    /**
     * Run sample queries to measure dashboard performance
     */
    private function runSampleDashboardQueries(): void
    {
        // Simulate dashboard queries
        DB::table('oauth_clients')
            ->select('health_status', 'is_active', 'environment', 'maintenance_mode')
            ->get();

        DB::table('oauth_notifications')
            ->select('type', 'acknowledged_at', 'created_at', 'oauth_client_id')
            ->whereNull('acknowledged_at')
            ->where('created_at', '>=', now()->subHours(24))
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Simulate client usage query
        if (DB::getSchemaBuilder()->hasTable('oauth_client_usage')) {
            DB::table('oauth_client_usage')
                ->where('date', now()->toDateString())
                ->sum('total_requests');
        }
    }

    /**
     * Convert memory limit string to bytes
     */
    private function convertToBytes(string $val): int
    {
        $val = trim($val);
        $last = strtolower($val[strlen($val) - 1]);
        $val = (int) $val;

        switch ($last) {
            case 'g':
                $val *= 1024;
                // no break
            case 'm':
                $val *= 1024;
                // no break
            case 'k':
                $val *= 1024;
        }

        return $val;
    }

    /**
     * Get query optimization suggestions
     */
    public function getQueryOptimizationSuggestions(): array
    {
        return [
            'oauth_clients' => [
                'frequently_used_filters' => ['health_status', 'is_active', 'environment'],
                'suggested_indexes' => [
                    'composite_dashboard' => ['is_active', 'health_status', 'environment'],
                    'health_monitoring' => ['health_check_enabled', 'last_health_check'],
                ],
            ],
            'oauth_notifications' => [
                'frequently_used_filters' => ['acknowledged_at', 'created_at', 'type'],
                'suggested_indexes' => [
                    'notification_center' => ['acknowledged_at', 'created_at'],
                    'type_filtering' => ['type', 'acknowledged_at'],
                ],
            ],
            'oauth_client_usage' => [
                'frequently_used_filters' => ['client_id', 'date'],
                'suggested_indexes' => [
                    'client_analytics' => ['client_id', 'date'],
                    'date_range_queries' => ['date', 'client_id'],
                ],
            ],
        ];
    }

    /**
     * Validate database indexes
     */
    public function validateDatabaseIndexes(): array
    {
        $indexStatus = [];
        $connection = DB::connection();

        $tables = ['oauth_clients', 'oauth_notifications', 'oauth_alert_rules'];

        foreach ($tables as $table) {
            if (!DB::getSchemaBuilder()->hasTable($table)) {
                continue;
            }

            try {
                $indexes = $connection->getDoctrineSchemaManager()
                    ->listTableIndexes($table);

                $indexStatus[$table] = [
                    'table_exists' => true,
                    'index_count' => count($indexes),
                    'indexes' => array_keys($indexes),
                ];
            } catch (\Exception $e) {
                $indexStatus[$table] = [
                    'table_exists' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $indexStatus;
    }
}