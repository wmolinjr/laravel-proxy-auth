<?php

namespace App\Jobs\OAuth;

use App\Models\OAuth\OAuthAccessToken;
use App\Models\OAuth\OAuthAuthorizationCode;
use App\Models\OAuth\OAuthClient;
use App\Models\OAuth\OAuthClientEvent;
use App\Models\OAuth\OAuthClientUsage;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OAuthUsageAggregationJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600; // 10 minutes
    public int $tries = 2;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private ?Carbon $date = null,
        private ?string $clientId = null
    ) {
        $this->date = $date ?? now()->yesterday();
        $this->onQueue('oauth-analytics');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting OAuth usage aggregation job', [
            'date' => $this->date->toDateString(),
            'client_id' => $this->clientId
        ]);

        try {
            if ($this->clientId) {
                // Aggregate usage for specific client
                $client = OAuthClient::find($this->clientId);
                if (!$client) {
                    Log::warning('Client not found for usage aggregation', [
                        'client_id' => $this->clientId
                    ]);
                    return;
                }

                $this->aggregateClientUsage($client);
            } else {
                // Aggregate usage for all active clients
                $clients = OAuthClient::enabled()->get();
                
                Log::info('Found clients for usage aggregation', [
                    'count' => $clients->count(),
                    'date' => $this->date->toDateString()
                ]);

                foreach ($clients as $client) {
                    try {
                        $this->aggregateClientUsage($client);
                    } catch (\Exception $e) {
                        Log::error('Error aggregating usage for client', [
                            'client_id' => $client->id,
                            'client_name' => $client->name,
                            'error' => $e->getMessage()
                        ]);
                        continue;
                    }
                }
            }

            Log::info('OAuth usage aggregation job completed successfully');

        } catch (\Exception $e) {
            Log::error('OAuth usage aggregation job failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Aggregate usage data for a specific client
     */
    private function aggregateClientUsage(OAuthClient $client): void
    {
        $dateString = $this->date->toDateString();
        $startOfDay = $this->date->startOfDay();
        $endOfDay = $this->date->endOfDay();

        Log::debug('Aggregating usage for client', [
            'client_id' => $client->id,
            'client_name' => $client->name,
            'date' => $dateString
        ]);

        // Get or create usage record
        $usage = OAuthClientUsage::firstOrNew([
            'client_id' => $client->id,
            'date' => $dateString,
        ]);

        // Authorization metrics from events
        $authEvents = $this->getEventCounts($client->id, $startOfDay, $endOfDay, [
            OAuthClientEvent::EVENT_AUTH_REQUEST,
            OAuthClientEvent::EVENT_AUTH_GRANTED,
            OAuthClientEvent::EVENT_AUTH_DENIED,
        ]);

        $usage->authorization_requests = $authEvents[OAuthClientEvent::EVENT_AUTH_REQUEST] ?? 0;
        $usage->successful_authorizations = $authEvents[OAuthClientEvent::EVENT_AUTH_GRANTED] ?? 0;
        $usage->failed_authorizations = $authEvents[OAuthClientEvent::EVENT_AUTH_DENIED] ?? 0;

        // Token metrics from events
        $tokenEvents = $this->getEventCounts($client->id, $startOfDay, $endOfDay, [
            OAuthClientEvent::EVENT_TOKEN_ISSUED,
            OAuthClientEvent::EVENT_TOKEN_REFRESHED,
        ]);

        $tokenRequests = ($tokenEvents[OAuthClientEvent::EVENT_TOKEN_ISSUED] ?? 0) + 
                        ($tokenEvents[OAuthClientEvent::EVENT_TOKEN_REFRESHED] ?? 0);
        
        $usage->token_requests = $tokenRequests;
        $usage->successful_tokens = $tokenRequests; // Assume all logged tokens were successful
        $usage->failed_tokens = $this->getEventCounts($client->id, $startOfDay, $endOfDay, [
            'token_failed'
        ])['token_failed'] ?? 0;

        // API call metrics from events
        $apiEvents = $this->getEventCounts($client->id, $startOfDay, $endOfDay, [
            OAuthClientEvent::EVENT_API_CALL,
        ]);

        $usage->api_calls = $apiEvents[OAuthClientEvent::EVENT_API_CALL] ?? 0;

        // User metrics from access tokens created on this date
        $userMetrics = $this->getUserMetrics($client->id, $startOfDay, $endOfDay);
        $usage->unique_users = $userMetrics['unique_users'];
        $usage->active_users = $userMetrics['active_users'];

        // Error metrics from events
        $errorCount = OAuthClientEvent::forClient($client->id)
                                     ->where('occurred_at', '>=', $startOfDay)
                                     ->where('occurred_at', '<=', $endOfDay)
                                     ->errors()
                                     ->count();

        $usage->error_count = $errorCount;

        // Calculate peak concurrent users (simplified estimate)
        $usage->peak_concurrent_users = max($usage->peak_concurrent_users, 
                                           intval($usage->unique_users * 0.3)); // Rough estimate

        // Calculate average response time from health check events
        $avgResponseTime = $this->getAverageResponseTime($client->id, $startOfDay, $endOfDay);
        if ($avgResponseTime > 0) {
            $usage->average_response_time = $avgResponseTime;
        }

        // Bytes transferred (placeholder - would need to be tracked separately)
        if ($usage->bytes_transferred === 0 && $usage->api_calls > 0) {
            $usage->bytes_transferred = $usage->api_calls * 1024; // Rough estimate: 1KB per API call
        }

        // Set last activity timestamp
        $lastActivity = $this->getLastActivityTime($client->id, $startOfDay, $endOfDay);
        if ($lastActivity) {
            $usage->last_activity_at = $lastActivity;
        }

        $usage->save();

        // Update client's last activity
        if ($lastActivity && $client->last_activity_at < $lastActivity) {
            $client->update(['last_activity_at' => $lastActivity]);
        }

        Log::debug('Usage aggregation completed for client', [
            'client_id' => $client->id,
            'date' => $dateString,
            'auth_requests' => $usage->authorization_requests,
            'successful_auths' => $usage->successful_authorizations,
            'token_requests' => $usage->token_requests,
            'api_calls' => $usage->api_calls,
            'unique_users' => $usage->unique_users,
            'errors' => $usage->error_count,
        ]);
    }

    /**
     * Get event counts for specific event types
     */
    private function getEventCounts(string $clientId, Carbon $start, Carbon $end, array $eventNames): array
    {
        $events = OAuthClientEvent::forClient($clientId)
                                 ->where('occurred_at', '>=', $start)
                                 ->where('occurred_at', '<=', $end)
                                 ->whereIn('event_name', $eventNames)
                                 ->selectRaw('event_name, COUNT(*) as count')
                                 ->groupBy('event_name')
                                 ->pluck('count', 'event_name')
                                 ->toArray();

        return $events;
    }

    /**
     * Get user metrics from access tokens
     */
    private function getUserMetrics(string $clientId, Carbon $start, Carbon $end): array
    {
        // Count unique users who got tokens on this date
        $uniqueUsers = OAuthAccessToken::where('client_id', $clientId)
                                      ->where('created_at', '>=', $start)
                                      ->where('created_at', '<=', $end)
                                      ->whereNotNull('user_id')
                                      ->distinct('user_id')
                                      ->count('user_id');

        // Count active users (those with valid tokens during this period)
        $activeUsers = OAuthAccessToken::where('client_id', $clientId)
                                      ->where('expires_at', '>', $start)
                                      ->where('created_at', '<=', $end)
                                      ->whereNotNull('user_id')
                                      ->distinct('user_id')
                                      ->count('user_id');

        return [
            'unique_users' => $uniqueUsers,
            'active_users' => $activeUsers,
        ];
    }

    /**
     * Get average response time from health check events
     */
    private function getAverageResponseTime(string $clientId, Carbon $start, Carbon $end): float
    {
        $events = OAuthClientEvent::forClient($clientId)
                                 ->where('occurred_at', '>=', $start)
                                 ->where('occurred_at', '<=', $end)
                                 ->where('event_name', OAuthClientEvent::EVENT_HEALTH_CHECK)
                                 ->whereNotNull('metadata')
                                 ->get();

        if ($events->isEmpty()) {
            return 0;
        }

        $totalResponseTime = 0;
        $validEvents = 0;

        foreach ($events as $event) {
            if (isset($event->metadata['response_time_ms'])) {
                $totalResponseTime += (float) $event->metadata['response_time_ms'];
                $validEvents++;
            }
        }

        return $validEvents > 0 ? $totalResponseTime / $validEvents : 0;
    }

    /**
     * Get last activity time for the day
     */
    private function getLastActivityTime(string $clientId, Carbon $start, Carbon $end): ?Carbon
    {
        $lastEvent = OAuthClientEvent::forClient($clientId)
                                    ->where('occurred_at', '>=', $start)
                                    ->where('occurred_at', '<=', $end)
                                    ->orderBy('occurred_at', 'desc')
                                    ->first();

        return $lastEvent?->occurred_at;
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('OAuth usage aggregation job failed permanently', [
            'date' => $this->date->toDateString(),
            'client_id' => $this->clientId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }

    /**
     * Get the tags that should be assigned to the job
     */
    public function tags(): array
    {
        $tags = ['oauth', 'usage-aggregation', "date:{$this->date->toDateString()}"];
        
        if ($this->clientId) {
            $tags[] = "client:{$this->clientId}";
        } else {
            $tags[] = 'batch-aggregation';
        }

        return $tags;
    }
}
