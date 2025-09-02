<?php

namespace App\Jobs\OAuth;

use App\Models\OAuth\OAuthClient;
use App\Services\OAuth\OAuthClientService;
use App\Services\OAuth\OAuthNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class OAuthHealthCheckJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300; // 5 minutes
    public int $tries = 3;
    public int $backoff = 60; // 1 minute between retries

    /**
     * Create a new job instance.
     */
    public function __construct(
        private ?string $clientId = null,
        private bool $forceCheck = false
    ) {
        // Set queue priority
        $this->onQueue('oauth-monitoring');
    }

    /**
     * Execute the job.
     */
    public function handle(OAuthClientService $clientService, OAuthNotificationService $notificationService): void
    {
        Log::info('Starting OAuth health check job', [
            'client_id' => $this->clientId,
            'force_check' => $this->forceCheck
        ]);

        try {
            if ($this->clientId) {
                // Check specific client
                $client = OAuthClient::find($this->clientId);
                if (!$client) {
                    Log::warning('OAuth client not found for health check', [
                        'client_id' => $this->clientId
                    ]);
                    return;
                }

                $this->performHealthCheck($client, $clientService, $notificationService);
            } else {
                // Check all clients that need health checks
                $clientsToCheck = $this->forceCheck ? 
                    $this->getAllClientsWithHealthCheck() :
                    $clientService->getClientsNeedingHealthCheck();

                Log::info('Found clients for health check', [
                    'count' => $clientsToCheck->count(),
                    'force_check' => $this->forceCheck
                ]);

                foreach ($clientsToCheck as $client) {
                    try {
                        $this->performHealthCheck($client, $clientService, $notificationService);
                        
                        // Small delay between checks to avoid overwhelming servers
                        if ($clientsToCheck->count() > 1) {
                            usleep(500000); // 0.5 second delay
                        }
                    } catch (\Exception $e) {
                        Log::error('Error checking individual client health', [
                            'client_id' => $client->id,
                            'client_name' => $client->name,
                            'error' => $e->getMessage()
                        ]);
                        continue;
                    }
                }
            }

            Log::info('OAuth health check job completed successfully');

        } catch (\Exception $e) {
            Log::error('OAuth health check job failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Perform health check on a single client
     */
    private function performHealthCheck(OAuthClient $client, OAuthClientService $clientService, OAuthNotificationService $notificationService): void
    {
        if (!$client->health_check_enabled || !$client->health_check_url) {
            return;
        }

        // Skip if client is in maintenance mode and not forcing check
        if ($client->maintenance_mode && !$this->forceCheck) {
            Log::debug('Skipping health check for client in maintenance mode', [
                'client_id' => $client->id,
                'client_name' => $client->name
            ]);
            return;
        }

        $previousStatus = $client->health_status;
        $result = $clientService->performHealthCheck($client);
        $newStatus = $client->fresh()->health_status;

        Log::info('Health check performed', [
            'client_id' => $client->id,
            'client_name' => $client->name,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'response_time_ms' => $result['response_time_ms'] ?? null,
            'status_code' => $result['status_code'] ?? null
        ]);

        // If status changed from healthy to unhealthy, dispatch notification
        if ($previousStatus === 'healthy' && in_array($newStatus, ['unhealthy', 'error'])) {
            Log::warning('Client health status deteriorated', [
                'client_id' => $client->id,
                'client_name' => $client->name,
                'from' => $previousStatus,
                'to' => $newStatus,
                'message' => $result['message'] ?? 'No message'
            ]);

            // Trigger health check failure notifications
            $notificationService->checkHealthCheckFailures($client);
        }

        // If status changed from unhealthy to healthy, log recovery
        if (in_array($previousStatus, ['unhealthy', 'error']) && $newStatus === 'healthy') {
            Log::info('Client health recovered', [
                'client_id' => $client->id,
                'client_name' => $client->name,
                'from' => $previousStatus,
                'to' => $newStatus
            ]);
        }
    }

    /**
     * Get all clients with health check enabled (for force checks)
     */
    private function getAllClientsWithHealthCheck()
    {
        return OAuthClient::enabled()
                         ->where('health_check_enabled', true)
                         ->whereNotNull('health_check_url')
                         ->get();
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('OAuth health check job failed permanently', [
            'client_id' => $this->clientId,
            'force_check' => $this->forceCheck,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        // TODO: Send alert to administrators when Phase 5 is implemented
    }

    /**
     * Calculate the number of seconds to wait before retrying
     */
    public function backoff(): array
    {
        return [60, 180, 300]; // 1 minute, 3 minutes, 5 minutes
    }

    /**
     * Get the tags that should be assigned to the job
     */
    public function tags(): array
    {
        $tags = ['oauth', 'health-check'];
        
        if ($this->clientId) {
            $tags[] = "client:{$this->clientId}";
        } else {
            $tags[] = 'batch-check';
        }

        if ($this->forceCheck) {
            $tags[] = 'force-check';
        }

        return $tags;
    }
}
