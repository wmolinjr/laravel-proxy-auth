<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OAuth\OAuthClient;
use App\Models\Admin\AuditLog;
use App\Services\OAuth\OAuthClientService;
use Illuminate\Http\Request;
use Illuminate\Http\Middleware\Authorize;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class OAuthClientController extends Controller
{
    public function __construct(
        private OAuthClientService $clientService
    ) {}

    public static function middleware(): array
    {
        return [
            'auth',
            new Authorize('can:oauth_clients.view', only: ['index', 'show', 'analytics', 'events', 'usage']),
            new Authorize('can:oauth_clients.create', only: ['create', 'store']),
            new Authorize('can:oauth_clients.edit', only: ['edit', 'update', 'toggleStatus', 'toggleMaintenance', 'healthCheck']),
            new Authorize('can:oauth_clients.delete', only: ['destroy']),
            new Authorize('can:oauth_clients.regenerate_secret', only: ['regenerateSecret']),
        ];
    }

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'status', 'environment', 'health_status', 'sort', 'order']);
        
        $clients = $this->clientService->getPaginatedClients($filters, 15);

        // Transform for frontend
        $clients->through(function ($client) {
            $todayUsage = $client->getTodayUsage();
            $recentErrors = $client->getRecentErrors(24);
            
            return [
                'id' => $client->id,
                'name' => $client->name,
                'identifier' => $client->identifier,
                'description' => $client->description,
                'is_active' => $client->is_active,
                'is_revoked' => $client->revoked,
                'is_confidential' => $client->is_confidential,
                'environment' => $client->environment,
                'health_status' => $client->health_status,
                'health_check_enabled' => $client->health_check_enabled,
                'last_health_check' => $client->last_health_check?->format('M d, Y H:i'),
                'last_activity_at' => $client->last_activity_at?->format('M d, Y H:i'),
                'maintenance_mode' => $client->maintenance_mode,
                'maintenance_message' => $client->maintenance_message,
                'redirect_uris' => $client->redirect_uris,
                'grants' => $client->grants,
                'scopes' => $client->scopes,
                'tags' => $client->tags,
                'contact_email' => $client->contact_email,
                'version' => $client->version,
                'access_tokens_count' => $client->access_tokens_count ?? 0,
                'authorization_codes_count' => $client->authorization_codes_count ?? 0,
                'created_at' => $client->created_at->format('M d, Y'),
                'updated_at' => $client->updated_at->format('M d, Y'),
                'creator' => $client->creator ? [
                    'name' => $client->creator->name,
                    'email' => $client->creator->email,
                ] : null,
                'today_usage' => $todayUsage ? [
                    'api_calls' => $todayUsage->api_calls,
                    'success_rate' => round($todayUsage->total_success_rate, 1),
                    'error_count' => $todayUsage->error_count,
                ] : null,
                'recent_errors_count' => $recentErrors->count(),
                'needs_health_check' => $client->needsHealthCheck(),
            ];
        });

        return Inertia::render('admin/oauth-clients/index', [
            'clients' => $clients,
            'filters' => $filters,
            'stats' => $this->clientService->getDashboardStats(),
            'availableEnvironments' => ['production', 'staging', 'development'],
            'availableHealthStatuses' => ['unknown', 'healthy', 'unhealthy', 'error'],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/oauth-clients/create', [
            'availableGrants' => [
                'authorization_code' => 'Authorization Code',
                'client_credentials' => 'Client Credentials',
                'refresh_token' => 'Refresh Token',
            ],
            'availableScopes' => [
                'read' => 'Read access',
                'write' => 'Write access',
                'openid' => 'OpenID Connect',
                'profile' => 'User profile',
                'email' => 'Email address',
            ],
            'availableEnvironments' => [
                'production' => 'Production',
                'staging' => 'Staging',
                'development' => 'Development',
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'redirect_uris' => 'required|array|min:1',
            'redirect_uris.*' => 'required|url',
            'grants' => 'required|array|min:1',
            'grants.*' => 'required|string|in:authorization_code,client_credentials,refresh_token',
            'scopes' => 'nullable|array',
            'scopes.*' => 'required|string|in:read,write,openid,profile,email',
            'is_confidential' => 'required|boolean',
            'environment' => 'required|string|in:production,staging,development',
            'contact_email' => 'nullable|email|max:255',
            'version' => 'nullable|string|max:50',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'health_check_enabled' => 'boolean',
            'health_check_url' => 'nullable|required_if:health_check_enabled,true|url|max:500',
            'health_check_interval' => 'nullable|integer|min:60|max:86400',
        ]);

        $client = $this->clientService->createClient($validated);

        // Store plain secret in session for one-time display (if generated)
        if ($client->secret) {
            session()->flash('client_secret', $client->secret);
        }

        return redirect()->route('oauth-clients.show', $client)
            ->with('success', 'Cliente OAuth criado com sucesso.');
    }

    public function show(OAuthClient $oauthClient): Response
    {
        $oauthClient->loadCount(['accessTokens', 'authorizationCodes', 'usageRecords', 'events'])
                   ->load(['creator', 'updater']);

        $todayUsage = $oauthClient->getTodayUsage();
        $recentEvents = $oauthClient->events()->recent(24)->limit(10)->get();
        $recentErrors = $oauthClient->getRecentErrors(24);
        $recentSecurityEvents = $oauthClient->getRecentSecurityEvents(24);

        return Inertia::render('admin/oauth-clients/show', [
            'client' => [
                'id' => $oauthClient->id,
                'identifier' => $oauthClient->identifier,
                'name' => $oauthClient->name,
                'description' => $oauthClient->description,
                'redirect_uris' => $oauthClient->redirect_uris,
                'grants' => $oauthClient->grants,
                'scopes' => $oauthClient->scopes,
                'is_confidential' => $oauthClient->is_confidential,
                'is_active' => $oauthClient->is_active,
                'is_revoked' => $oauthClient->revoked,
                'environment' => $oauthClient->environment,
                'health_status' => $oauthClient->health_status,
                'health_check_enabled' => $oauthClient->health_check_enabled,
                'health_check_url' => $oauthClient->health_check_url,
                'health_check_interval' => $oauthClient->health_check_interval,
                'last_health_check' => $oauthClient->last_health_check?->format('M d, Y H:i:s'),
                'last_error_message' => $oauthClient->last_error_message,
                'last_activity_at' => $oauthClient->last_activity_at?->format('M d, Y H:i:s'),
                'maintenance_mode' => $oauthClient->maintenance_mode,
                'maintenance_message' => $oauthClient->maintenance_message,
                'tags' => $oauthClient->tags,
                'contact_email' => $oauthClient->contact_email,
                'version' => $oauthClient->version,
                'access_tokens_count' => $oauthClient->access_tokens_count,
                'authorization_codes_count' => $oauthClient->authorization_codes_count,
                'usage_records_count' => $oauthClient->usage_records_count,
                'events_count' => $oauthClient->events_count,
                'created_at' => $oauthClient->created_at->format('M d, Y H:i:s'),
                'updated_at' => $oauthClient->updated_at->format('M d, Y H:i:s'),
                'has_secret' => !is_null($oauthClient->secret),
                'creator' => $oauthClient->creator ? [
                    'name' => $oauthClient->creator->name,
                    'email' => $oauthClient->creator->email,
                ] : null,
                'updater' => $oauthClient->updater ? [
                    'name' => $oauthClient->updater->name,
                    'email' => $oauthClient->updater->email,
                ] : null,
                'needs_health_check' => $oauthClient->needsHealthCheck(),
            ],
            'todayUsage' => $todayUsage ? [
                'date' => $todayUsage->date->format('M d, Y'),
                'authorization_requests' => $todayUsage->authorization_requests,
                'successful_authorizations' => $todayUsage->successful_authorizations,
                'failed_authorizations' => $todayUsage->failed_authorizations,
                'token_requests' => $todayUsage->token_requests,
                'successful_tokens' => $todayUsage->successful_tokens,
                'failed_tokens' => $todayUsage->failed_tokens,
                'api_calls' => $todayUsage->api_calls,
                'unique_users' => $todayUsage->unique_users,
                'error_count' => $todayUsage->error_count,
                'authorization_success_rate' => round($todayUsage->authorization_success_rate, 1),
                'token_success_rate' => round($todayUsage->token_success_rate, 1),
                'total_success_rate' => round($todayUsage->total_success_rate, 1),
            ] : null,
            'recentEvents' => $recentEvents->map(function ($event) {
                return [
                    'id' => $event->id,
                    'event_type' => $event->event_type,
                    'event_name' => $event->event_name,
                    'description' => $event->description,
                    'severity' => $event->severity,
                    'user' => $event->user ? [
                        'name' => $event->user->name,
                        'email' => $event->user->email,
                    ] : null,
                    'ip_address' => $event->ip_address,
                    'occurred_at' => $event->occurred_at->format('M d, Y H:i:s'),
                    'is_error' => $event->isError(),
                    'is_critical' => $event->isCritical(),
                    'is_security' => $event->isSecurityEvent(),
                ];
            }),
            'recentTokens' => $oauthClient->accessTokens()
                ->with('user')
                ->latest()
                ->limit(10)
                ->get()
                ->map(function ($token) {
                    return [
                        'id' => $token->id,
                        'user' => $token->user ? [
                            'id' => $token->user->id,
                            'name' => $token->user->name,
                            'email' => $token->user->email,
                        ] : null,
                        'scopes' => $token->scopes,
                        'expires_at' => $token->expires_at?->format('M d, Y H:i'),
                        'created_at' => $token->created_at->format('M d, Y H:i'),
                        'is_valid' => $token->isValid(),
                    ];
                }),
            'errorsSummary' => [
                'recent_errors_count' => $recentErrors->count(),
                'recent_security_events_count' => $recentSecurityEvents->count(),
                'critical_events_count' => $recentEvents->where('severity', 'critical')->count(),
            ],
            'clientSecret' => session('client_secret'),
        ]);
    }

    public function edit(OAuthClient $oauthClient): Response
    {
        // Authorization handled by middleware
        // $this->authorize('oauth_clients.edit');
        
        return Inertia::render('admin/oauth-clients/edit', [
            'client' => [
                'id' => $oauthClient->id,
                'name' => $oauthClient->name,
                'description' => $oauthClient->description,
                'redirect_uris' => $oauthClient->redirect_uris,
                'grants' => $oauthClient->grants,
                'scopes' => $oauthClient->scopes,
                'is_confidential' => $oauthClient->is_confidential,
                'is_active' => $oauthClient->is_active,
            ],
            'availableGrants' => [
                'authorization_code' => 'Authorization Code',
                'client_credentials' => 'Client Credentials',
                'refresh_token' => 'Refresh Token',
            ],
            'availableScopes' => [
                'read' => 'Read access',
                'write' => 'Write access',
                'openid' => 'OpenID Connect',
                'profile' => 'User profile',
                'email' => 'Email address',
            ],
        ]);
    }

    public function update(Request $request, OAuthClient $oauthClient)
    {
        // Authorization handled by middleware
        // $this->authorize('oauth_clients.edit');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'redirect_uris' => 'required|array|min:1',
            'redirect_uris.*' => 'required|url',
            'grants' => 'required|array|min:1',
            'grants.*' => 'required|string|in:authorization_code,client_credentials,refresh_token',
            'scopes' => 'nullable|array',
            'scopes.*' => 'required|string|in:read,write,openid,profile,email',
            'is_active' => 'boolean',
        ]);

        $oauthClient->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'redirect_uris' => $validated['redirect_uris'],
            'grants' => $validated['grants'],
            'scopes' => $validated['scopes'] ?? [],
            'is_active' => $validated['is_active'] ?? $oauthClient->is_active,
        ]);

        return redirect()->route('oauth-clients.show', $oauthClient)
            ->with('success', 'Cliente OAuth atualizado com sucesso.');
    }

    public function destroy(OAuthClient $oauthClient)
    {
        // Authorization handled by middleware
        // $this->authorize('oauth_clients.delete');

        // Revoke all tokens for this client
        $oauthClient->accessTokens()->delete();
        $oauthClient->authorizationCodes()->delete();
        
        $oauthClient->delete();

        return redirect()->route('oauth-clients.index')
            ->with('success', 'Cliente OAuth e todos os tokens associados foram excluídos.');
    }

    public function regenerateSecret(OAuthClient $oauthClient)
    {
        // Authorization handled by middleware
        // $this->authorize('oauth_clients.regenerate_secret');

        if (!$oauthClient->is_confidential) {
            return back()->with('error', 'Apenas clientes confidenciais possuem secret.');
        }

        // Generate new secret
        $newSecret = Str::random(64);
        
        $oauthClient->update([
            'secret' => hash('sha256', $newSecret),
        ]);

        // Optionally revoke existing tokens (security measure)
        if (request()->boolean('revoke_tokens')) {
            $oauthClient->accessTokens()->delete();
            $oauthClient->authorizationCodes()->delete();
        }

        // Store plain secret in session for one-time display
        session()->flash('client_secret', $newSecret);

        AuditLog::logEvent(
            'oauth_client_secret_regenerated',
            'OAuthClient',
            $oauthClient->id,
            null,
            ['revoke_tokens' => request()->boolean('revoke_tokens')]
        );

        return back()->with('success', 'Secret do cliente regenerado com sucesso.');
    }

    public function revokeTokens(OAuthClient $oauthClient)
    {
        // Authorization handled by middleware
        // $this->authorize('oauth_clients.edit');

        $tokenCount = $oauthClient->accessTokens()->count();
        $codeCount = $oauthClient->authorizationCodes()->count();

        $oauthClient->accessTokens()->delete();
        $oauthClient->authorizationCodes()->delete();

        AuditLog::logEvent(
            'oauth_client_tokens_revoked',
            'OAuthClient',
            $oauthClient->id,
            null,
            [
                'revoked_tokens' => $tokenCount,
                'revoked_codes' => $codeCount,
            ]
        );

        return back()->with('success', "Revogados {$tokenCount} tokens e {$codeCount} códigos de autorização.");
    }

    /**
     * Toggle client active status
     */
    public function toggleStatus(OAuthClient $oauthClient)
    {
        $client = $this->clientService->toggleClientStatus($oauthClient);

        $message = $client->is_active ? 
            'Cliente ativado com sucesso.' : 
            'Cliente desativado com sucesso.';

        return back()->with('success', $message);
    }

    /**
     * Toggle maintenance mode
     */
    public function toggleMaintenance(Request $request, OAuthClient $oauthClient)
    {
        $request->validate([
            'maintenance_message' => 'nullable|string|max:500'
        ]);

        $client = $this->clientService->toggleMaintenanceMode(
            $oauthClient,
            $request->get('maintenance_message')
        );

        $message = $client->maintenance_mode ? 
            'Modo de manutenção ativado.' : 
            'Modo de manutenção desativado.';

        return back()->with('success', $message);
    }

    /**
     * Perform manual health check
     */
    public function healthCheck(OAuthClient $oauthClient)
    {
        $result = $this->clientService->performHealthCheck($oauthClient);

        if ($result['status'] === 'disabled') {
            return back()->with('warning', $result['message']);
        }

        if (in_array($result['status'], ['healthy'])) {
            return back()->with('success', $result['message'] . 
                (isset($result['response_time_ms']) ? 
                    " (Tempo de resposta: {$result['response_time_ms']}ms)" : ''));
        }

        return back()->with('error', $result['message']);
    }

    /**
     * Show analytics page for a client
     */
    public function analytics(Request $request, OAuthClient $oauthClient): Response
    {
        $days = $request->get('days', 30);
        $startDate = now()->subDays($days);
        $endDate = now();

        $usage = $oauthClient->usageRecords()
                            ->dateRange($startDate, $endDate)
                            ->orderBy('date')
                            ->get();

        $events = $oauthClient->events()
                             ->where('occurred_at', '>=', $startDate)
                             ->selectRaw('DATE(occurred_at) as date, event_type, COUNT(*) as count')
                             ->groupBy('date', 'event_type')
                             ->orderBy('date')
                             ->get();

        return Inertia::render('admin/oauth-clients/analytics', [
            'client' => [
                'id' => $oauthClient->id,
                'name' => $oauthClient->name,
                'identifier' => $oauthClient->identifier,
            ],
            'usageData' => $usage->map(function ($record) {
                return [
                    'date' => $record->date->format('Y-m-d'),
                    'authorization_requests' => $record->authorization_requests,
                    'successful_authorizations' => $record->successful_authorizations,
                    'failed_authorizations' => $record->failed_authorizations,
                    'token_requests' => $record->token_requests,
                    'successful_tokens' => $record->successful_tokens,
                    'failed_tokens' => $record->failed_tokens,
                    'api_calls' => $record->api_calls,
                    'unique_users' => $record->unique_users,
                    'error_count' => $record->error_count,
                    'authorization_success_rate' => round($record->authorization_success_rate, 1),
                    'token_success_rate' => round($record->token_success_rate, 1),
                    'total_success_rate' => round($record->total_success_rate, 1),
                ];
            }),
            'eventsData' => $events->groupBy('date')->map(function ($dayEvents, $date) {
                return [
                    'date' => $date,
                    'events' => $dayEvents->groupBy('event_type')->map(function ($typeEvents) {
                        return $typeEvents->sum('count');
                    }),
                ];
            })->values(),
            'filters' => [
                'days' => $days,
            ],
            'summary' => [
                'total_requests' => $usage->sum('authorization_requests') + $usage->sum('token_requests'),
                'total_api_calls' => $usage->sum('api_calls'),
                'total_errors' => $usage->sum('error_count'),
                'avg_success_rate' => $usage->avg('total_success_rate'),
                'total_unique_users' => $usage->max('unique_users'),
            ],
        ]);
    }

    /**
     * Show events page for a client
     */
    public function events(Request $request, OAuthClient $oauthClient): Response
    {
        $filters = $request->only(['event_type', 'severity', 'hours']);
        
        $query = $oauthClient->events()->with('user')->orderBy('occurred_at', 'desc');

        if ($filters['event_type'] ?? false) {
            $query->ofType($filters['event_type']);
        }

        if ($filters['severity'] ?? false) {
            $query->ofSeverity($filters['severity']);
        }

        if ($filters['hours'] ?? false) {
            $query->recent((int)$filters['hours']);
        }

        $events = $query->paginate(20);

        return Inertia::render('admin/oauth-clients/events', [
            'client' => [
                'id' => $oauthClient->id,
                'name' => $oauthClient->name,
                'identifier' => $oauthClient->identifier,
            ],
            'events' => $events->through(function ($event) {
                return [
                    'id' => $event->id,
                    'event_type' => $event->event_type,
                    'event_name' => $event->event_name,
                    'description' => $event->description,
                    'severity' => $event->severity,
                    'ip_address' => $event->ip_address,
                    'user_agent' => $event->user_agent,
                    'metadata' => $event->metadata,
                    'user' => $event->user ? [
                        'id' => $event->user->id,
                        'name' => $event->user->name,
                        'email' => $event->user->email,
                    ] : null,
                    'occurred_at' => $event->occurred_at->format('M d, Y H:i:s'),
                    'is_error' => $event->isError(),
                    'is_critical' => $event->isCritical(),
                    'is_security' => $event->isSecurityEvent(),
                ];
            }),
            'filters' => $filters,
            'availableEventTypes' => [
                'authentication', 'authorization', 'token', 'api', 
                'health', 'security', 'system', 'error', 'warning', 'info', 'success'
            ],
            'availableSeverities' => ['low', 'medium', 'high', 'critical'],
        ]);
    }

    /**
     * Show usage statistics page for a client
     */
    public function usage(Request $request, OAuthClient $oauthClient): Response
    {
        $days = $request->get('days', 30);
        $startDate = now()->subDays($days);
        
        $usage = $oauthClient->usageRecords()
                            ->dateRange($startDate, now())
                            ->orderBy('date', 'desc')
                            ->paginate(15);

        return Inertia::render('admin/oauth-clients/usage', [
            'client' => [
                'id' => $oauthClient->id,
                'name' => $oauthClient->name,
                'identifier' => $oauthClient->identifier,
            ],
            'usage' => $usage->through(function ($record) {
                return [
                    'id' => $record->id,
                    'date' => $record->date->format('M d, Y'),
                    'authorization_requests' => $record->authorization_requests,
                    'successful_authorizations' => $record->successful_authorizations,
                    'failed_authorizations' => $record->failed_authorizations,
                    'token_requests' => $record->token_requests,
                    'successful_tokens' => $record->successful_tokens,
                    'failed_tokens' => $record->failed_tokens,
                    'active_users' => $record->active_users,
                    'unique_users' => $record->unique_users,
                    'api_calls' => $record->api_calls,
                    'bytes_transferred' => $record->bytes_transferred,
                    'average_response_time' => $record->average_response_time,
                    'peak_concurrent_users' => $record->peak_concurrent_users,
                    'error_count' => $record->error_count,
                    'authorization_success_rate' => round($record->authorization_success_rate, 1),
                    'token_success_rate' => round($record->token_success_rate, 1),
                    'total_success_rate' => round($record->total_success_rate, 1),
                ];
            }),
            'filters' => ['days' => $days],
        ]);
    }
}
