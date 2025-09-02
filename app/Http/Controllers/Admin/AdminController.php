<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\AuditLog;
use App\Models\Admin\SecurityEvent;
use App\Models\OAuth\OAuthClient;
use App\Models\OAuth\OAuthAccessToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Middleware\Authorize;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    public static function middleware(): array
    {
        return [
            'auth',
            new Authorize('can:dashboard.view'),
        ];
    }

    public function dashboard(): Response
    {
        $stats = $this->getDashboardStats();

        return Inertia::render('admin/dashboard', [
            'stats' => $stats,
            'recentEvents' => SecurityEvent::with(['user', 'client'])
                ->latest()
                ->limit(10)
                ->get()
                ->map(function ($event) {
                    return [
                        'id' => $event->id,
                        'event_type' => $event->event_type,
                        'event_description' => $event->event_description,
                        'severity' => $event->severity,
                        'severity_color' => $event->severity_color,
                        'user' => $event->user ? [
                            'id' => $event->user->id,
                            'name' => $event->user->name,
                            'email' => $event->user->email,
                        ] : null,
                        'ip_address' => $event->ip_address,
                        'created_at' => $event->created_at->format('M d, Y H:i'),
                        'is_resolved' => $event->is_resolved,
                    ];
                }),
            'recentAuditLogs' => AuditLog::with('user')
                ->latest()
                ->limit(10)
                ->get()
                ->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'event_type' => $log->event_type,
                        'entity_type' => $log->entity_type,
                        'entity_id' => $log->entity_id,
                        'user' => $log->user ? [
                            'id' => $log->user->id,
                            'name' => $log->user->name,
                            'email' => $log->user->email,
                        ] : null,
                        'ip_address' => $log->ip_address,
                        'created_at' => $log->created_at->format('M d, Y H:i'),
                    ];
                }),
        ]);
    }

    public function analytics(): Response
    {

        $analytics = [
            'users' => [
                'total' => User::count(),
                'active' => User::active()->count(),
                'admins' => User::admins()->count(),
                'recent_registrations' => User::where('created_at', '>=', now()->subDays(30))->count(),
                'growth_chart' => $this->getUserGrowthChart(),
            ],
            'oauth' => [
                'clients' => OAuthClient::count(),
                'active_tokens' => OAuthAccessToken::valid()->count(),
                'token_usage_chart' => $this->getTokenUsageChart(),
                'client_usage' => OAuthClient::withCount(['accessTokens as active_tokens_count' => function ($query) {
                    $query->valid();
                }])->get()->map(function ($client) {
                    return [
                        'name' => $client->name,
                        'active_tokens' => $client->active_tokens_count,
                    ];
                }),
            ],
            'security' => [
                'events_last_30_days' => SecurityEvent::where('created_at', '>=', now()->subDays(30))->count(),
                'unresolved_events' => SecurityEvent::unresolved()->count(),
                'high_severity_events' => SecurityEvent::highSeverity()->where('created_at', '>=', now()->subDays(30))->count(),
                'security_chart' => $this->getSecurityEventsChart(),
                'events_by_type' => SecurityEvent::where('created_at', '>=', now()->subDays(30))
                    ->selectRaw('event_type, count(*) as count')
                    ->groupBy('event_type')
                    ->pluck('count', 'event_type')
                    ->toArray(),
            ],
            'system' => [
                'audit_logs_last_30_days' => AuditLog::where('created_at', '>=', now()->subDays(30))->count(),
                'database_size' => $this->getDatabaseSize(),
                'uptime' => $this->getSystemUptime(),
            ]
        ];

        return Inertia::render('admin/analytics', compact('analytics'));
    }

    private function getDashboardStats(): array
    {
        return [
            'users' => [
                'total' => User::count(),
                'active' => User::active()->count(),
                'new_today' => User::whereDate('created_at', today())->count(),
                'growth_percentage' => $this->calculateGrowthPercentage(User::class, 30),
            ],
            'oauth_clients' => [
                'total' => OAuthClient::count(),
                'active' => OAuthClient::where('revoked', false)->count(),
            ],
            'tokens' => [
                'active' => OAuthAccessToken::valid()->count(),
                'issued_today' => OAuthAccessToken::whereDate('created_at', today())->count(),
            ],
            'security' => [
                'events_today' => SecurityEvent::whereDate('created_at', today())->count(),
                'unresolved_events' => SecurityEvent::unresolved()->count(),
                'high_severity' => SecurityEvent::highSeverity()->where('created_at', '>=', now()->subDays(7))->count(),
            ],
            'audit_logs' => [
                'today' => AuditLog::whereDate('created_at', today())->count(),
                'this_week' => AuditLog::where('created_at', '>=', now()->subDays(7))->count(),
            ]
        ];
    }

    private function calculateGrowthPercentage(string $model, int $days): float
    {
        $currentPeriod = $model::where('created_at', '>=', now()->subDays($days))->count();
        $previousPeriod = $model::where('created_at', '>=', now()->subDays($days * 2))
            ->where('created_at', '<', now()->subDays($days))
            ->count();

        if ($previousPeriod === 0) {
            return $currentPeriod > 0 ? 100.0 : 0.0;
        }

        return round((($currentPeriod - $previousPeriod) / $previousPeriod) * 100, 1);
    }

    private function getUserGrowthChart(): array
    {
        $days = 30;
        $data = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $count = User::whereDate('created_at', $date->toDateString())->count();

            $data[] = [
                'date' => $date->format('M d'),
                'users' => $count,
            ];
        }

        return $data;
    }

    private function getTokenUsageChart(): array
    {
        $days = 30;
        $data = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $count = OAuthAccessToken::whereDate('created_at', $date->toDateString())->count();

            $data[] = [
                'date' => $date->format('M d'),
                'tokens' => $count,
            ];
        }

        return $data;
    }

    private function getSecurityEventsChart(): array
    {
        $days = 30;
        $data = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $count = SecurityEvent::whereDate('created_at', $date->toDateString())->count();

            $data[] = [
                'date' => $date->format('M d'),
                'events' => $count,
            ];
        }

        return $data;
    }

    private function getDatabaseSize(): string
    {
        try {
            $size = \DB::select("
                SELECT pg_size_pretty(pg_database_size(current_database())) as size
            ")[0]->size ?? 'Unknown';

            return $size;
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    private function getSystemUptime(): string
    {
        try {
            $uptime = shell_exec('uptime -p');
            return trim($uptime ?: 'Unknown');
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }
}
