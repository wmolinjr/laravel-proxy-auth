<?php

namespace App\Console\Commands;

use App\Services\Performance\OAuthPerformanceMonitoringService;
use Illuminate\Console\Command;

class OAuthMonitorPerformance extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'oauth:monitor-performance
                            {--report : Generate and display full performance report}
                            {--cache : Monitor cache performance only}
                            {--database : Monitor database performance only}
                            {--memory : Monitor memory usage only}
                            {--recommendations : Show performance recommendations}
                            {--log : Log performance metrics}
                            {--validate-indexes : Validate database indexes}';

    /**
     * The console command description.
     */
    protected $description = 'Monitor OAuth system performance and generate optimization recommendations';

    /**
     * Execute the console command.
     */
    public function handle(OAuthPerformanceMonitoringService $performanceService): int
    {
        $this->info('🔍 OAuth Performance Monitoring');
        $this->info('=' . str_repeat('=', 50));

        try {
            // Handle specific monitoring options
            if ($this->option('cache')) {
                $this->monitorCache($performanceService);
                return 0;
            }

            if ($this->option('database')) {
                $this->monitorDatabase($performanceService);
                return 0;
            }

            if ($this->option('memory')) {
                $this->monitorMemory($performanceService);
                return 0;
            }

            if ($this->option('recommendations')) {
                $this->showRecommendations($performanceService);
                return 0;
            }

            if ($this->option('validate-indexes')) {
                $this->validateIndexes($performanceService);
                return 0;
            }

            if ($this->option('log')) {
                $performanceService->logPerformanceMetrics();
                $this->info('✅ Performance metrics logged successfully');
                return 0;
            }

            // Default: show full report or quick overview
            if ($this->option('report')) {
                $this->showFullReport($performanceService);
            } else {
                $this->showQuickOverview($performanceService);
            }

            return 0;
        } catch (\Exception $e) {
            $this->error("❌ Error monitoring performance: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Show quick performance overview
     */
    private function showQuickOverview(OAuthPerformanceMonitoringService $performanceService): void
    {
        $this->info('📊 Quick Performance Overview');
        $this->newLine();

        // Cache performance
        $cacheMetrics = $performanceService->monitorCachePerformance();
        $cacheStatus = $cacheMetrics['meets_target'] ? '✅' : '⚠️';
        $this->line("Cache Hit Rate: {$cacheStatus} {$cacheMetrics['hit_rate_percent']}% (Target: {$cacheMetrics['target_hit_rate_percent']}%)");

        // Memory usage
        $memoryMetrics = $performanceService->monitorMemoryUsage();
        $memoryStatus = $memoryMetrics['usage_percentage'] < 80 ? '✅' : '⚠️';
        $this->line("Memory Usage: {$memoryStatus} {$memoryMetrics['usage_percentage']}% ({$memoryMetrics['current_usage_mb']}MB)");

        // Database performance (quick check)
        $dbMetrics = $performanceService->monitorDatabasePerformance();
        $dbStatus = $dbMetrics['slow_queries_count'] == 0 ? '✅' : '⚠️';
        $this->line("Database Performance: {$dbStatus} {$dbMetrics['average_query_time_ms']}ms avg, {$dbMetrics['slow_queries_count']} slow queries");

        $this->newLine();
        $this->info('💡 Use --report for detailed analysis or --recommendations for optimization suggestions');
    }

    /**
     * Show full performance report
     */
    private function showFullReport(OAuthPerformanceMonitoringService $performanceService): void
    {
        $report = $performanceService->getPerformanceReport();

        $this->info('📈 Comprehensive Performance Report');
        $this->info('Generated: ' . $report['timestamp']);
        $this->newLine();

        // Database Performance
        $this->info('🗃️ Database Performance:');
        $dbMetrics = $report['database_performance'];
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Queries', $dbMetrics['total_queries']],
                ['Total Time', $dbMetrics['total_time_ms'] . 'ms'],
                ['Average Query Time', $dbMetrics['average_query_time_ms'] . 'ms'],
                ['Slow Queries', $dbMetrics['slow_queries_count']],
            ]
        );

        if ($dbMetrics['slow_queries_count'] > 0) {
            $this->warn('⚠️ Slow Queries Detected:');
            foreach ($dbMetrics['slow_queries'] as $query) {
                $this->line("   • {$query['time_ms']}ms: " . substr($query['query'], 0, 80) . '...');
            }
        }

        $this->newLine();

        // Cache Performance
        $this->info('🚀 Cache Performance:');
        $cacheMetrics = $report['cache_performance'];
        $this->table(
            ['Metric', 'Value'],
            [
                ['Cache Hits', $cacheMetrics['cache_hits']],
                ['Cache Misses', $cacheMetrics['cache_misses']],
                ['Hit Rate', $cacheMetrics['hit_rate_percent'] . '%'],
                ['Target Hit Rate', $cacheMetrics['target_hit_rate_percent'] . '%'],
                ['Meets Target', $cacheMetrics['meets_target'] ? 'Yes ✅' : 'No ⚠️'],
            ]
        );

        $this->newLine();

        // Memory Usage
        $this->info('💾 Memory Usage:');
        $memoryMetrics = $report['memory_usage'];
        $this->table(
            ['Metric', 'Value'],
            [
                ['Current Usage', $memoryMetrics['current_usage_mb'] . 'MB'],
                ['Peak Usage', $memoryMetrics['peak_usage_mb'] . 'MB'],
                ['Memory Limit', $memoryMetrics['memory_limit_mb'] . 'MB'],
                ['Usage %', $memoryMetrics['usage_percentage'] . '%'],
                ['Peak %', $memoryMetrics['peak_percentage'] . '%'],
            ]
        );

        $this->newLine();

        // Recommendations
        if (!empty($report['recommendations'])) {
            $this->showRecommendationsTable($report['recommendations']);
        } else {
            $this->info('✅ No performance issues detected!');
        }
    }

    /**
     * Monitor cache performance only
     */
    private function monitorCache(OAuthPerformanceMonitoringService $performanceService): void
    {
        $this->info('🚀 Cache Performance Analysis');
        $this->newLine();

        $cacheMetrics = $performanceService->monitorCachePerformance();

        $this->table(
            ['Metric', 'Value', 'Status'],
            [
                ['Cache Hits', $cacheMetrics['cache_hits'], ''],
                ['Cache Misses', $cacheMetrics['cache_misses'], ''],
                ['Hit Rate', $cacheMetrics['hit_rate_percent'] . '%', $cacheMetrics['meets_target'] ? '✅' : '⚠️'],
                ['Target', $cacheMetrics['target_hit_rate_percent'] . '%', ''],
            ]
        );

        $this->newLine();
        $this->info('🔑 Cache Key Status:');
        foreach ($cacheMetrics['key_status'] as $key => $status) {
            $icon = $status === 'hit' ? '✅' : '❌';
            $this->line("   {$icon} {$key}: {$status}");
        }
    }

    /**
     * Monitor database performance only
     */
    private function monitorDatabase(OAuthPerformanceMonitoringService $performanceService): void
    {
        $this->info('🗃️ Database Performance Analysis');
        $this->newLine();

        $dbMetrics = $performanceService->monitorDatabasePerformance();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Queries', $dbMetrics['total_queries']],
                ['Total Execution Time', $dbMetrics['total_time_ms'] . 'ms'],
                ['Average Query Time', $dbMetrics['average_query_time_ms'] . 'ms'],
                ['Slow Queries (>' . ($performanceService::class)::SLOW_QUERY_THRESHOLD . 'ms)', $dbMetrics['slow_queries_count']],
            ]
        );

        if ($dbMetrics['slow_queries_count'] > 0) {
            $this->newLine();
            $this->warn('⚠️ Slow Queries Found:');
            foreach ($dbMetrics['slow_queries'] as $index => $query) {
                $this->line(($index + 1) . ". {$query['time_ms']}ms");
                $this->line("   Query: " . substr($query['query'], 0, 100) . '...');
                if (!empty($query['bindings'])) {
                    $this->line("   Bindings: " . json_encode($query['bindings']));
                }
                $this->newLine();
            }
        }
    }

    /**
     * Monitor memory usage only
     */
    private function monitorMemory(OAuthPerformanceMonitoringService $performanceService): void
    {
        $this->info('💾 Memory Usage Analysis');
        $this->newLine();

        $memoryMetrics = $performanceService->monitorMemoryUsage();

        $this->table(
            ['Metric', 'Value', 'Status'],
            [
                ['Current Usage', $memoryMetrics['current_usage_mb'] . 'MB', $memoryMetrics['usage_percentage'] < 80 ? '✅' : '⚠️'],
                ['Peak Usage', $memoryMetrics['peak_usage_mb'] . 'MB', $memoryMetrics['peak_percentage'] < 90 ? '✅' : '⚠️'],
                ['Memory Limit', $memoryMetrics['memory_limit_mb'] . 'MB', ''],
                ['Usage Percentage', $memoryMetrics['usage_percentage'] . '%', ''],
                ['Peak Percentage', $memoryMetrics['peak_percentage'] . '%', ''],
            ]
        );

        if ($memoryMetrics['usage_percentage'] > 80) {
            $this->warn('⚠️ High memory usage detected. Consider optimizing memory usage or increasing memory limit.');
        }
    }

    /**
     * Show performance recommendations
     */
    private function showRecommendations(OAuthPerformanceMonitoringService $performanceService): void
    {
        $this->info('💡 Performance Optimization Recommendations');
        $this->newLine();

        $recommendations = $performanceService->generatePerformanceRecommendations();

        if (empty($recommendations)) {
            $this->info('✅ No performance issues detected! Your OAuth system is performing well.');
            return;
        }

        $this->showRecommendationsTable($recommendations);
    }

    /**
     * Show recommendations in table format
     */
    private function showRecommendationsTable(array $recommendations): void
    {
        $this->info('💡 Performance Recommendations:');

        $tableData = [];
        foreach ($recommendations as $rec) {
            $priority = match($rec['priority']) {
                'high' => '🔴 High',
                'medium' => '🟡 Medium',
                'low' => '🟢 Low',
                default => $rec['priority']
            };

            $tableData[] = [
                $rec['type'],
                $priority,
                $rec['title'],
                substr($rec['description'], 0, 50) . '...',
            ];
        }

        $this->table(
            ['Type', 'Priority', 'Issue', 'Description'],
            $tableData
        );

        $this->newLine();
        $this->info('📝 Detailed Actions:');
        foreach ($recommendations as $index => $rec) {
            $this->line(($index + 1) . ". {$rec['title']}");
            $this->line("   Action: {$rec['action']}");
            $this->newLine();
        }
    }

    /**
     * Validate database indexes
     */
    private function validateIndexes(OAuthPerformanceMonitoringService $performanceService): void
    {
        $this->info('🔍 Database Index Validation');
        $this->newLine();

        $indexStatus = $performanceService->validateDatabaseIndexes();

        foreach ($indexStatus as $table => $status) {
            if ($status['table_exists']) {
                $this->info("✅ {$table}:");
                $this->line("   Indexes: {$status['index_count']}");
                foreach ($status['indexes'] as $index) {
                    $this->line("   • {$index}");
                }
            } else {
                $this->warn("❌ {$table}: Table not found or error occurred");
                if (isset($status['error'])) {
                    $this->line("   Error: {$status['error']}");
                }
            }
            $this->newLine();
        }

        // Show optimization suggestions
        $suggestions = $performanceService->getQueryOptimizationSuggestions();
        $this->info('💡 Index Optimization Suggestions:');
        foreach ($suggestions as $table => $suggestion) {
            $this->line("📋 {$table}:");
            $this->line("   Frequently filtered by: " . implode(', ', $suggestion['frequently_used_filters']));
            foreach ($suggestion['suggested_indexes'] as $name => $columns) {
                $this->line("   💡 Suggested index '{$name}': [" . implode(', ', $columns) . "]");
            }
            $this->newLine();
        }
    }
}
