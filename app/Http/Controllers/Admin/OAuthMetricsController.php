<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\OAuthMetric;
use App\Services\OAuthAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OAuthMetricsController extends Controller
{
    public static function middleware(): array
    {
        return [
            'auth',
            \Illuminate\Http\Middleware\Authorize::class . ':can:metrics.view',
        ];
    }

    /**
     * Display OAuth metrics dashboard
     */
    public function index(Request $request): Response
    {
        $days = $request->get('days', 7);
        $startDate = now()->subDays($days);

        return Inertia::render('admin/oauth-metrics/index', [
            'metrics' => [
                'overview' => $this->getOverviewMetrics($days),
                'performance' => $this->getPerformanceMetrics($days),
                'endpoints' => $this->getEndpointMetrics($days),
                'clients' => $this->getClientMetrics($days),
                'errors' => $this->getErrorMetrics($days),
                'audit' => OAuthAuditService::getAuditSummary($days),
            ],
            'timeRange' => $days,
            'charts' => [
                'requestsOverTime' => $this->getRequestsOverTime($days),
                'responseTimesTrend' => $this->getResponseTimesTrend($days),
                'errorRatesTrend' => $this->getErrorRatesTrend($days),
            ],
        ]);
    }

    /**
     * Get overview metrics
     */
    private function getOverviewMetrics(int $days): array
    {
        $startDate = now()->subDays($days);
        $previousStartDate = now()->subDays($days * 2);

        $current = OAuthMetric::where('created_at', '>=', $startDate);
        $previous = OAuthMetric::whereBetween('created_at', [$previousStartDate, $startDate]);

        return [
            'total_requests' => [
                'value' => $current->count(),
                'change' => $this->calculateChange(
                    $current->count(),
                    $previous->count()
                ),
            ],
            'avg_response_time' => [
                'value' => round($current->avg('response_time_ms'), 2),
                'change' => $this->calculateChange(
                    $current->avg('response_time_ms'),
                    $previous->avg('response_time_ms')
                ),
            ],
            'success_rate' => [
                'value' => $this->calculateSuccessRate($current),
                'change' => $this->calculateChange(
                    $this->calculateSuccessRate($current),
                    $this->calculateSuccessRate($previous)
                ),
            ],
            'error_rate' => [
                'value' => $this->calculateErrorRate($current),
                'change' => $this->calculateChange(
                    $this->calculateErrorRate($current),
                    $this->calculateErrorRate($previous)
                ),
            ],
        ];
    }

    /**
     * Get performance metrics
     */
    private function getPerformanceMetrics(int $days): array
    {
        $startDate = now()->subDays($days);

        return [
            'p50_response_time' => DB::table('oauth_metrics')
                ->where('created_at', '>=', $startDate)
                ->select(DB::raw('PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY response_time_ms) as p50'))
                ->value('p50'),
            'p95_response_time' => DB::table('oauth_metrics')
                ->where('created_at', '>=', $startDate)
                ->select(DB::raw('PERCENTILE_CONT(0.95) WITHIN GROUP (ORDER BY response_time_ms) as p95'))
                ->value('p95'),
            'p99_response_time' => DB::table('oauth_metrics')
                ->where('created_at', '>=', $startDate)
                ->select(DB::raw('PERCENTILE_CONT(0.99) WITHIN GROUP (ORDER BY response_time_ms) as p99'))
                ->value('p99'),
            'max_response_time' => OAuthMetric::where('created_at', '>=', $startDate)
                ->max('response_time_ms'),
            'slow_requests_count' => OAuthMetric::where('created_at', '>=', $startDate)
                ->slowRequests(1000)
                ->count(),
        ];
    }

    /**
     * Get endpoint-specific metrics
     */
    private function getEndpointMetrics(int $days): array
    {
        $startDate = now()->subDays($days);

        return OAuthMetric::where('created_at', '>=', $startDate)
            ->select('endpoint')
            ->selectRaw('COUNT(*) as requests')
            ->selectRaw('AVG(response_time_ms) as avg_response_time')
            ->selectRaw('COUNT(*) FILTER(WHERE status_code >= 400) as errors')
            ->selectRaw('COUNT(*) FILTER(WHERE status_code BETWEEN 200 AND 299) as successful')
            ->groupBy('endpoint')
            ->get()
            ->map(function ($metric) {
                return [
                    'endpoint' => $metric->endpoint,
                    'requests' => $metric->requests,
                    'avg_response_time' => round($metric->avg_response_time, 2),
                    'error_count' => $metric->errors,
                    'success_rate' => round(($metric->successful / $metric->requests) * 100, 2),
                ];
            })
            ->toArray();
    }

    /**
     * Get client-specific metrics
     */
    private function getClientMetrics(int $days): array
    {
        $startDate = now()->subDays($days);

        return OAuthMetric::where('created_at', '>=', $startDate)
            ->whereNotNull('client_id')
            ->select('client_id')
            ->selectRaw('COUNT(*) as requests')
            ->selectRaw('AVG(response_time_ms) as avg_response_time')
            ->selectRaw('COUNT(*) FILTER(WHERE status_code >= 400) as errors')
            ->selectRaw('COUNT(*) FILTER(WHERE status_code BETWEEN 200 AND 299) as successful')
            ->groupBy('client_id')
            ->orderByDesc('requests')
            ->limit(10)
            ->get()
            ->map(function ($metric) {
                return [
                    'client_id' => $metric->client_id,
                    'requests' => $metric->requests,
                    'avg_response_time' => round($metric->avg_response_time, 2),
                    'error_count' => $metric->errors,
                    'success_rate' => round(($metric->successful / $metric->requests) * 100, 2),
                ];
            })
            ->toArray();
    }

    /**
     * Get error metrics
     */
    private function getErrorMetrics(int $days): array
    {
        $startDate = now()->subDays($days);

        return OAuthMetric::where('created_at', '>=', $startDate)
            ->errors()
            ->select('error_type', 'status_code')
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('endpoint')
            ->groupBy('error_type', 'status_code', 'endpoint')
            ->orderByDesc('count')
            ->limit(20)
            ->get()
            ->toArray();
    }

    /**
     * Get requests over time for chart
     */
    private function getRequestsOverTime(int $days): array
    {
        $startDate = now()->subDays($days);
        $interval = $days > 30 ? 'day' : ($days > 7 ? '4 hours' : '1 hour');

        return OAuthMetric::where('created_at', '>=', $startDate)
            ->select(DB::raw("DATE_TRUNC('$interval', created_at) as time_bucket"))
            ->selectRaw('endpoint')
            ->selectRaw('COUNT(*) as requests')
            ->groupBy('time_bucket', 'endpoint')
            ->orderBy('time_bucket')
            ->get()
            ->groupBy('endpoint')
            ->toArray();
    }

    /**
     * Get response times trend for chart
     */
    private function getResponseTimesTrend(int $days): array
    {
        $startDate = now()->subDays($days);
        $interval = $days > 30 ? 'day' : ($days > 7 ? '4 hours' : '1 hour');

        return OAuthMetric::where('created_at', '>=', $startDate)
            ->select(DB::raw("DATE_TRUNC('$interval', created_at) as time_bucket"))
            ->selectRaw('AVG(response_time_ms) as avg_response_time')
            ->selectRaw('endpoint')
            ->groupBy('time_bucket', 'endpoint')
            ->orderBy('time_bucket')
            ->get()
            ->groupBy('endpoint')
            ->toArray();
    }

    /**
     * Get error rates trend for chart
     */
    private function getErrorRatesTrend(int $days): array
    {
        $startDate = now()->subDays($days);
        $interval = $days > 30 ? 'day' : ($days > 7 ? '4 hours' : '1 hour');

        return OAuthMetric::where('created_at', '>=', $startDate)
            ->select(DB::raw("DATE_TRUNC('$interval', created_at) as time_bucket"))
            ->selectRaw('COUNT(*) as total_requests')
            ->selectRaw('COUNT(*) FILTER(WHERE status_code >= 400) as error_requests')
            ->selectRaw('endpoint')
            ->groupBy('time_bucket', 'endpoint')
            ->orderBy('time_bucket')
            ->get()
            ->map(function ($metric) {
                $metric->error_rate = $metric->total_requests > 0 
                    ? round(($metric->error_requests / $metric->total_requests) * 100, 2)
                    : 0;
                return $metric;
            })
            ->groupBy('endpoint')
            ->toArray();
    }

    /**
     * Calculate percentage change
     */
    private function calculateChange(?float $current, ?float $previous): float
    {
        if (!$current || !$previous || $previous == 0) {
            return 0;
        }
        
        return round((($current - $previous) / $previous) * 100, 2);
    }

    /**
     * Calculate success rate from query builder
     */
    private function calculateSuccessRate($query): float
    {
        $total = $query->count();
        if ($total === 0) return 100.0;

        $successful = $query->successful()->count();
        return round(($successful / $total) * 100, 2);
    }

    /**
     * Calculate error rate from query builder
     */
    private function calculateErrorRate($query): float
    {
        $total = $query->count();
        if ($total === 0) return 0.0;

        $errors = $query->errors()->count();
        return round(($errors / $total) * 100, 2);
    }
}