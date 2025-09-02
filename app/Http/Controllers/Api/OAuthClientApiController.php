<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OAuth\OAuthClient;
use App\Services\OAuth\OAuthClientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OAuthClientApiController extends Controller
{
    public function __construct(
        private OAuthClientService $clientService
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('can:oauth_clients.view')->only(['index', 'show', 'analytics', 'events', 'usage']);
        $this->middleware('can:oauth_clients.create')->only(['store']);
        $this->middleware('can:oauth_clients.edit')->only(['update', 'toggleStatus', 'toggleMaintenance', 'healthCheck']);
        $this->middleware('can:oauth_clients.delete')->only(['destroy']);
    }

    /**
     * Get paginated list of OAuth clients
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'status', 'environment', 'health_status', 'sort', 'order']);
        $clients = $this->clientService->getPaginatedClients($filters, $request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $clients,
            'stats' => $this->clientService->getDashboardStats(),
        ]);
    }

    /**
     * Show specific OAuth client
     */
    public function show(OAuthClient $client): JsonResponse
    {
        $client->loadCount(['accessTokens', 'authorizationCodes', 'usageRecords', 'events'])
               ->load(['creator', 'updater']);

        $todayUsage = $client->getTodayUsage();
        $recentEvents = $client->events()->recent(24)->limit(10)->get();
        $recentErrors = $client->getRecentErrors(24);

        return response()->json([
            'success' => true,
            'data' => [
                'client' => $client,
                'today_usage' => $todayUsage,
                'recent_events' => $recentEvents,
                'recent_errors_count' => $recentErrors->count(),
                'needs_health_check' => $client->needsHealthCheck(),
            ]
        ]);
    }

    /**
     * Create new OAuth client
     */
    public function store(Request $request): JsonResponse
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

        return response()->json([
            'success' => true,
            'message' => 'OAuth client created successfully',
            'data' => [
                'client' => $client,
                'secret' => $client->secret, // Return secret only once
            ]
        ], 201);
    }

    /**
     * Update OAuth client
     */
    public function update(Request $request, OAuthClient $client): JsonResponse
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
            'environment' => 'required|string|in:production,staging,development',
            'contact_email' => 'nullable|email|max:255',
            'version' => 'nullable|string|max:50',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'health_check_enabled' => 'boolean',
            'health_check_url' => 'nullable|required_if:health_check_enabled,true|url|max:500',
            'health_check_interval' => 'nullable|integer|min:60|max:86400',
            'regenerate_secret' => 'boolean',
        ]);

        $updatedClient = $this->clientService->updateClient($client, $validated);

        $response = [
            'success' => true,
            'message' => 'OAuth client updated successfully',
            'data' => ['client' => $updatedClient]
        ];

        // Include new secret if regenerated
        if ($validated['regenerate_secret'] ?? false) {
            $response['data']['new_secret'] = $updatedClient->secret;
        }

        return response()->json($response);
    }

    /**
     * Delete OAuth client
     */
    public function destroy(OAuthClient $client): JsonResponse
    {
        // Revoke all tokens and codes
        $client->accessTokens()->delete();
        $client->authorizationCodes()->delete();
        
        $client->delete();

        return response()->json([
            'success' => true,
            'message' => 'OAuth client deleted successfully'
        ]);
    }

    /**
     * Toggle client active status
     */
    public function toggleStatus(OAuthClient $client): JsonResponse
    {
        $updatedClient = $this->clientService->toggleClientStatus($client);

        return response()->json([
            'success' => true,
            'message' => $updatedClient->is_active ? 'Client activated' : 'Client deactivated',
            'data' => ['client' => $updatedClient]
        ]);
    }

    /**
     * Toggle maintenance mode
     */
    public function toggleMaintenance(Request $request, OAuthClient $client): JsonResponse
    {
        $request->validate([
            'maintenance_message' => 'nullable|string|max:500'
        ]);

        $updatedClient = $this->clientService->toggleMaintenanceMode(
            $client,
            $request->get('maintenance_message')
        );

        return response()->json([
            'success' => true,
            'message' => $updatedClient->maintenance_mode ? 
                'Maintenance mode enabled' : 'Maintenance mode disabled',
            'data' => ['client' => $updatedClient]
        ]);
    }

    /**
     * Perform health check
     */
    public function healthCheck(OAuthClient $client): JsonResponse
    {
        $result = $this->clientService->performHealthCheck($client);

        return response()->json([
            'success' => !in_array($result['status'], ['error', 'unhealthy']),
            'message' => $result['message'],
            'data' => $result
        ]);
    }

    /**
     * Get client analytics data
     */
    public function analytics(Request $request, OAuthClient $client): JsonResponse
    {
        $days = $request->get('days', 30);
        $startDate = now()->subDays($days);
        $endDate = now();

        $usage = $client->usageRecords()
                       ->dateRange($startDate, $endDate)
                       ->orderBy('date')
                       ->get();

        $events = $client->events()
                        ->where('occurred_at', '>=', $startDate)
                        ->selectRaw('DATE(occurred_at) as date, event_type, COUNT(*) as count')
                        ->groupBy('date', 'event_type')
                        ->orderBy('date')
                        ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'usage_data' => $usage,
                'events_data' => $events->groupBy('date'),
                'summary' => [
                    'total_requests' => $usage->sum('authorization_requests') + $usage->sum('token_requests'),
                    'total_api_calls' => $usage->sum('api_calls'),
                    'total_errors' => $usage->sum('error_count'),
                    'avg_success_rate' => $usage->avg('total_success_rate'),
                    'total_unique_users' => $usage->max('unique_users'),
                ],
            ]
        ]);
    }

    /**
     * Get client events
     */
    public function events(Request $request, OAuthClient $client): JsonResponse
    {
        $filters = $request->only(['event_type', 'severity', 'hours']);
        
        $query = $client->events()->with('user')->orderBy('occurred_at', 'desc');

        if ($filters['event_type'] ?? false) {
            $query->ofType($filters['event_type']);
        }

        if ($filters['severity'] ?? false) {
            $query->ofSeverity($filters['severity']);
        }

        if ($filters['hours'] ?? false) {
            $query->recent((int)$filters['hours']);
        }

        $events = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $events
        ]);
    }

    /**
     * Get client usage statistics
     */
    public function usage(Request $request, OAuthClient $client): JsonResponse
    {
        $days = $request->get('days', 30);
        $startDate = now()->subDays($days);
        
        $usage = $client->usageRecords()
                       ->dateRange($startDate, now())
                       ->orderBy('date', 'desc')
                       ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $usage
        ]);
    }

    /**
     * Get dashboard statistics
     */
    public function stats(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->clientService->getDashboardStats()
        ]);
    }

    /**
     * Batch health check for all clients
     */
    public function batchHealthCheck(): JsonResponse
    {
        $clientsNeedingCheck = $this->clientService->getClientsNeedingHealthCheck();
        $results = [];

        foreach ($clientsNeedingCheck as $client) {
            $results[] = [
                'client_id' => $client->id,
                'client_name' => $client->name,
                'result' => $this->clientService->performHealthCheck($client)
            ];
        }

        return response()->json([
            'success' => true,
            'message' => "Health check performed on {$clientsNeedingCheck->count()} clients",
            'data' => $results
        ]);
    }

    /**
     * Get clients needing attention (unhealthy, errors, etc.)
     */
    public function attention(): JsonResponse
    {
        $unhealthyClients = OAuthClient::unhealthy()->with(['creator', 'updater'])->get();
        $maintenanceClients = OAuthClient::inMaintenance()->with(['creator', 'updater'])->get();
        $clientsNeedingHealthCheck = $this->clientService->getClientsNeedingHealthCheck();

        return response()->json([
            'success' => true,
            'data' => [
                'unhealthy_clients' => $unhealthyClients,
                'maintenance_clients' => $maintenanceClients,
                'clients_needing_health_check' => $clientsNeedingHealthCheck,
                'total_attention_needed' => $unhealthyClients->count() + 
                                          $maintenanceClients->count() + 
                                          $clientsNeedingHealthCheck->count(),
            ]
        ]);
    }
}