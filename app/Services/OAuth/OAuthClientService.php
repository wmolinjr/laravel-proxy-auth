<?php

namespace App\Services\OAuth;

use App\Models\OAuth\OAuthClient;
use App\Models\OAuth\OAuthClientEvent;
use App\Models\OAuth\OAuthClientUsage;
use App\Models\User;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Client\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OAuthClientService
{
    /**
     * Get paginated clients with optional filters
     */
    public function getPaginatedClients(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = OAuthClient::query();

        // Apply filters
        if (isset($filters['status'])) {
            match ($filters['status']) {
                'active' => $query->enabled(),
                'inactive' => $query->where('is_active', false),
                'maintenance' => $query->inMaintenance(),
                'unhealthy' => $query->unhealthy(),
                'revoked' => $query->where('revoked', true),
                default => null
            };
        }

        if (isset($filters['environment'])) {
            $query->where('environment', $filters['environment']);
        }

        if (isset($filters['health_status'])) {
            $query->healthStatus($filters['health_status']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                  ->orWhere('description', 'ILIKE', "%{$search}%")
                  ->orWhere('contact_email', 'ILIKE', "%{$search}%");
            });
        }

        return $query->with(['creator', 'updater'])
                    ->orderBy('created_at', 'desc')
                    ->paginate($perPage);
    }

    /**
     * Create a new OAuth client
     */
    public function createClient(array $data, ?User $user = null): OAuthClient
    {
        $user = $user ?? Auth::user();

        return DB::transaction(function () use ($data, $user) {
            // Generate client ID and secret
            $clientId = $data['id'] ?? Str::uuid()->toString();
            $clientSecret = $data['generate_secret'] ?? true ? 
                Str::random(40) : null;

            $client = OAuthClient::create([
                'id' => $clientId,
                'identifier' => $clientId,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'secret' => $clientSecret,
                'redirect' => implode(',', $data['redirect_uris'] ?? []),
                'redirect_uris' => $data['redirect_uris'] ?? [],
                'grants' => $data['grants'] ?? ['authorization_code'],
                'scopes' => $data['scopes'] ?? ['read'],
                'is_confidential' => $data['is_confidential'] ?? true,
                'is_active' => true, // New clients are active by default
                'revoked' => false,
                'personal_access_client' => false,
                'password_client' => false,
                'health_check_url' => $data['health_check_url'] ?? null,
                'health_check_interval' => $data['health_check_interval'] ?? 300,
                'health_check_enabled' => $data['health_check_enabled'] ?? false,
                'health_status' => 'unknown', // Will be updated on first health check
                'last_health_check' => null,
                'last_error_message' => null,
                'last_activity_at' => now(),
                'maintenance_mode' => false,
                'maintenance_message' => null,
                'environment' => $data['environment'] ?? 'development',
                'tags' => $data['tags'] ?? [],
                'contact_email' => $data['contact_email'] ?? null,
                'website_url' => $data['website_url'] ?? null,
                'max_concurrent_tokens' => $data['max_concurrent_tokens'] ?? 1000,
                'rate_limit_per_minute' => $data['rate_limit_per_minute'] ?? 100,
                'version' => $data['version'] ?? null,
                'created_by' => $user?->id,
                'updated_by' => $user?->id,
            ]);

            // Log creation event
            $this->logEvent($client, OAuthClientEvent::EVENT_CLIENT_CREATED, [
                'description' => 'OAuth client created successfully',
                'event_type' => OAuthClientEvent::TYPE_SYSTEM,
                'severity' => OAuthClientEvent::SEVERITY_LOW,
                'user_id' => $user?->id,
                'metadata' => [
                    'client_name' => $client->name,
                    'environment' => $client->environment,
                    'has_secret' => !empty($client->secret),
                ]
            ]);

            return $client;
        });
    }

    /**
     * Update an existing OAuth client
     */
    public function updateClient(OAuthClient $client, array $data, ?User $user = null): OAuthClient
    {
        $user = $user ?? Auth::user();

        return DB::transaction(function () use ($client, $data, $user) {
            $originalData = $client->toArray();

            $client->update([
                'name' => $data['name'] ?? $client->name,
                'description' => $data['description'] ?? $client->description,
                'redirect' => isset($data['redirect_uris']) ? 
                    implode(',', $data['redirect_uris']) : $client->redirect,
                'redirect_uris' => $data['redirect_uris'] ?? $client->redirect_uris,
                'grants' => $data['grants'] ?? $client->grants,
                'scopes' => $data['scopes'] ?? $client->scopes,
                'is_confidential' => $data['is_confidential'] ?? $client->is_confidential,
                'health_check_url' => $data['health_check_url'] ?? $client->health_check_url,
                'health_check_interval' => $data['health_check_interval'] ?? $client->health_check_interval,
                'health_check_enabled' => $data['health_check_enabled'] ?? $client->health_check_enabled,
                'environment' => $data['environment'] ?? $client->environment,
                'tags' => $data['tags'] ?? $client->tags,
                'contact_email' => $data['contact_email'] ?? $client->contact_email,
                'website_url' => $data['website_url'] ?? $client->website_url,
                'max_concurrent_tokens' => $data['max_concurrent_tokens'] ?? $client->max_concurrent_tokens,
                'rate_limit_per_minute' => $data['rate_limit_per_minute'] ?? $client->rate_limit_per_minute,
                'version' => $data['version'] ?? $client->version,
                'updated_by' => $user?->id,
            ]);

            // Regenerate secret if requested
            if ($data['regenerate_secret'] ?? false) {
                $client->update(['secret' => Str::random(40)]);
            }

            // Log update event
            $this->logEvent($client, OAuthClientEvent::EVENT_CLIENT_UPDATED, [
                'description' => 'OAuth client updated successfully',
                'event_type' => OAuthClientEvent::TYPE_SYSTEM,
                'severity' => OAuthClientEvent::SEVERITY_LOW,
                'user_id' => $user?->id,
                'metadata' => [
                    'changes' => $this->getChanges($originalData, $client->toArray()),
                    'regenerated_secret' => $data['regenerate_secret'] ?? false,
                ]
            ]);

            return $client;
        });
    }

    /**
     * Toggle client active status
     */
    public function toggleClientStatus(OAuthClient $client, ?User $user = null): OAuthClient
    {
        $user = $user ?? Auth::user();
        $wasActive = $client->is_active;

        $client->update([
            'is_active' => !$wasActive,
            'updated_by' => $user?->id,
        ]);

        $this->logEvent($client, 
            $client->is_active ? OAuthClientEvent::EVENT_CLIENT_ACTIVATED : OAuthClientEvent::EVENT_CLIENT_DEACTIVATED,
            [
                'description' => $client->is_active ? 
                    'OAuth client activated' : 'OAuth client deactivated',
                'event_type' => OAuthClientEvent::TYPE_SYSTEM,
                'severity' => OAuthClientEvent::SEVERITY_MEDIUM,
                'user_id' => $user?->id,
            ]
        );

        return $client;
    }

    /**
     * Toggle maintenance mode
     */
    public function toggleMaintenanceMode(OAuthClient $client, ?string $message = null, ?User $user = null): OAuthClient
    {
        $user = $user ?? Auth::user();
        $wasInMaintenance = $client->maintenance_mode;

        $client->update([
            'maintenance_mode' => !$wasInMaintenance,
            'maintenance_message' => !$wasInMaintenance ? $message : null,
            'updated_by' => $user?->id,
        ]);

        $this->logEvent($client, 
            $client->maintenance_mode ? OAuthClientEvent::EVENT_MAINTENANCE_START : OAuthClientEvent::EVENT_MAINTENANCE_END,
            [
                'description' => $client->maintenance_mode ? 
                    'Maintenance mode enabled' : 'Maintenance mode disabled',
                'event_type' => OAuthClientEvent::TYPE_SYSTEM,
                'severity' => OAuthClientEvent::SEVERITY_MEDIUM,
                'user_id' => $user?->id,
                'metadata' => [
                    'maintenance_message' => $message,
                ]
            ]
        );

        return $client;
    }

    /**
     * Perform health check on client
     */
    public function performHealthCheck(OAuthClient $client): array
    {
        if (!$client->health_check_enabled || !$client->health_check_url) {
            return [
                'status' => 'disabled',
                'message' => 'Health check is disabled or URL not configured'
            ];
        }

        try {
            $startTime = microtime(true);
            
            $response = Http::timeout(30)
                           ->connectTimeout(10)
                           ->get($client->health_check_url);

            $responseTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

            $isHealthy = $response->successful();
            $status = $isHealthy ? 'healthy' : 'unhealthy';
            $message = $isHealthy ? 'Health check passed' : 'Health check failed';

            $client->update([
                'health_status' => $status,
                'last_health_check' => now(),
                'last_error_message' => $isHealthy ? null : $response->body(),
            ]);

            $this->logEvent($client, 
                $isHealthy ? OAuthClientEvent::EVENT_HEALTH_UP : OAuthClientEvent::EVENT_HEALTH_DOWN,
                [
                    'description' => $message,
                    'event_type' => OAuthClientEvent::TYPE_HEALTH,
                    'severity' => $isHealthy ? OAuthClientEvent::SEVERITY_LOW : OAuthClientEvent::SEVERITY_HIGH,
                    'metadata' => [
                        'response_time_ms' => round($responseTime, 2),
                        'status_code' => $response->status(),
                        'url' => $client->health_check_url,
                    ]
                ]
            );

            return [
                'status' => $status,
                'message' => $message,
                'response_time_ms' => round($responseTime, 2),
                'status_code' => $response->status(),
            ];

        } catch (Exception $e) {
            $client->update([
                'health_status' => 'error',
                'last_health_check' => now(),
                'last_error_message' => $e->getMessage(),
            ]);

            $this->logEvent($client, OAuthClientEvent::EVENT_ERROR_OCCURRED, [
                'description' => 'Health check error: ' . $e->getMessage(),
                'event_type' => OAuthClientEvent::TYPE_ERROR,
                'severity' => OAuthClientEvent::SEVERITY_HIGH,
                'metadata' => [
                    'error' => $e->getMessage(),
                    'url' => $client->health_check_url,
                ]
            ]);

            return [
                'status' => 'error',
                'message' => 'Health check error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get or create today's usage record for a client
     */
    public function getTodayUsageRecord(OAuthClient $client): OAuthClientUsage
    {
        return OAuthClientUsage::firstOrCreate([
            'client_id' => $client->id,
            'date' => now()->toDateString(),
        ]);
    }

    /**
     * Record usage event
     */
    public function recordUsage(OAuthClient $client, string $type, array $data = []): void
    {
        $usage = $this->getTodayUsageRecord($client);

        $updates = ['last_activity_at' => now()];

        match ($type) {
            'authorization_request' => $updates['authorization_requests'] = DB::raw('authorization_requests + 1'),
            'authorization_success' => $updates['successful_authorizations'] = DB::raw('successful_authorizations + 1'),
            'authorization_failed' => $updates['failed_authorizations'] = DB::raw('failed_authorizations + 1'),
            'token_request' => $updates['token_requests'] = DB::raw('token_requests + 1'),
            'token_success' => $updates['successful_tokens'] = DB::raw('successful_tokens + 1'),
            'token_failed' => $updates['failed_tokens'] = DB::raw('failed_tokens + 1'),
            'api_call' => [
                $updates['api_calls'] = DB::raw('api_calls + 1'),
                $updates['bytes_transferred'] = DB::raw('bytes_transferred + ' . ($data['bytes'] ?? 0)),
            ],
            'error' => $updates['error_count'] = DB::raw('error_count + 1'),
            default => null
        };

        if ($updates) {
            $usage->update($updates);
            $client->touchActivity();
        }
    }

    /**
     * Log an event for a client
     */
    public function logEvent(OAuthClient $client, string $eventName, array $data = []): OAuthClientEvent
    {
        return OAuthClientEvent::create([
            'client_id' => $client->id,
            'user_id' => $data['user_id'] ?? Auth::id(),
            'event_type' => $data['event_type'] ?? OAuthClientEvent::TYPE_INFO,
            'event_name' => $eventName,
            'description' => $data['description'] ?? null,
            'severity' => $data['severity'] ?? OAuthClientEvent::SEVERITY_LOW,
            'ip_address' => $data['ip_address'] ?? request()->ip(),
            'user_agent' => $data['user_agent'] ?? request()->userAgent(),
            'metadata' => $data['metadata'] ?? [],
            'occurred_at' => $data['occurred_at'] ?? now(),
        ]);
    }

    /**
     * Get clients that need health checks
     */
    public function getClientsNeedingHealthCheck(): Collection
    {
        return OAuthClient::enabled()
                         ->where('health_check_enabled', true)
                         ->whereNotNull('health_check_url')
                         ->where(function ($query) {
                             $query->whereNull('last_health_check')
                                   ->orWhereRaw('last_health_check + INTERVAL health_check_interval SECOND < NOW()');
                         })
                         ->get();
    }

    /**
     * Get dashboard statistics
     */
    public function getDashboardStats(): array
    {
        return [
            'total_clients' => OAuthClient::count(),
            'active_clients' => OAuthClient::enabled()->count(),
            'unhealthy_clients' => OAuthClient::unhealthy()->count(),
            'maintenance_clients' => OAuthClient::inMaintenance()->count(),
            'total_usage_today' => OAuthClientUsage::where('date', now()->toDateString())
                                                  ->sum('api_calls'),
            'recent_errors' => OAuthClientEvent::errors()->recent(24)->count(),
        ];
    }

    /**
     * Get changes between two arrays
     */
    private function getChanges(array $original, array $updated): array
    {
        $changes = [];
        
        foreach ($updated as $key => $value) {
            if (isset($original[$key]) && $original[$key] !== $value) {
                $changes[$key] = [
                    'from' => $original[$key],
                    'to' => $value,
                ];
            }
        }
        
        return $changes;
    }
}