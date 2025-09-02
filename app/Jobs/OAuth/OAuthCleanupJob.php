<?php

namespace App\Jobs\OAuth;

use App\Models\OAuth\OAuthAccessToken;
use App\Models\OAuth\OAuthAuthorizationCode;
use App\Models\OAuth\OAuthClientEvent;
use App\Models\OAuth\OAuthClientUsage;
use App\Models\OAuth\OAuthRefreshToken;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class OAuthCleanupJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800; // 30 minutes
    public int $tries = 2;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private array $cleanupTypes = ['tokens', 'events', 'usage'],
        private int $retentionDays = 90
    ) {
        $this->onQueue('oauth-maintenance');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting OAuth cleanup job', [
            'cleanup_types' => $this->cleanupTypes,
            'retention_days' => $this->retentionDays
        ]);

        $totalCleaned = 0;

        try {
            if (in_array('tokens', $this->cleanupTypes)) {
                $totalCleaned += $this->cleanupExpiredTokens();
            }

            if (in_array('events', $this->cleanupTypes)) {
                $totalCleaned += $this->cleanupOldEvents();
            }

            if (in_array('usage', $this->cleanupTypes)) {
                $totalCleaned += $this->cleanupOldUsageRecords();
            }

            Log::info('OAuth cleanup job completed successfully', [
                'total_records_cleaned' => $totalCleaned
            ]);

        } catch (\Exception $e) {
            Log::error('OAuth cleanup job failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Clean up expired tokens and authorization codes
     */
    private function cleanupExpiredTokens(): int
    {
        $totalCleaned = 0;
        $now = now();

        Log::info('Starting token cleanup');

        // Clean up expired access tokens
        $expiredTokensCount = OAuthAccessToken::where('expires_at', '<', $now)
                                            ->where('revoked', true)
                                            ->count();

        if ($expiredTokensCount > 0) {
            $deletedTokens = OAuthAccessToken::where('expires_at', '<', $now)
                                           ->where('revoked', true)
                                           ->delete();

            Log::info('Cleaned up expired access tokens', [
                'count' => $deletedTokens
            ]);

            $totalCleaned += $deletedTokens;
        }

        // Clean up expired authorization codes
        $expiredCodesCount = OAuthAuthorizationCode::where('expires_at', '<', $now)
                                                 ->count();

        if ($expiredCodesCount > 0) {
            $deletedCodes = OAuthAuthorizationCode::where('expires_at', '<', $now)
                                                ->delete();

            Log::info('Cleaned up expired authorization codes', [
                'count' => $deletedCodes
            ]);

            $totalCleaned += $deletedCodes;
        }

        // Clean up expired refresh tokens
        $expiredRefreshTokensCount = OAuthRefreshToken::where('expires_at', '<', $now)
                                                    ->where('revoked', true)
                                                    ->count();

        if ($expiredRefreshTokensCount > 0) {
            $deletedRefreshTokens = OAuthRefreshToken::where('expires_at', '<', $now)
                                                   ->where('revoked', true)
                                                   ->delete();

            Log::info('Cleaned up expired refresh tokens', [
                'count' => $deletedRefreshTokens
            ]);

            $totalCleaned += $deletedRefreshTokens;
        }

        // Clean up very old revoked tokens (beyond retention period)
        $oldRevokedCutoff = $now->copy()->subDays($this->retentionDays);
        
        $oldRevokedTokensCount = OAuthAccessToken::where('revoked', true)
                                               ->where('updated_at', '<', $oldRevokedCutoff)
                                               ->count();

        if ($oldRevokedTokensCount > 0) {
            $deletedOldTokens = OAuthAccessToken::where('revoked', true)
                                              ->where('updated_at', '<', $oldRevokedCutoff)
                                              ->delete();

            Log::info('Cleaned up old revoked tokens', [
                'count' => $deletedOldTokens,
                'cutoff_date' => $oldRevokedCutoff->toDateString()
            ]);

            $totalCleaned += $deletedOldTokens;
        }

        return $totalCleaned;
    }

    /**
     * Clean up old events
     */
    private function cleanupOldEvents(): int
    {
        $cutoffDate = now()->subDays($this->retentionDays);
        
        Log::info('Starting events cleanup', [
            'cutoff_date' => $cutoffDate->toDateString(),
            'retention_days' => $this->retentionDays
        ]);

        // Keep critical events longer (double retention period)
        $criticalCutoffDate = now()->subDays($this->retentionDays * 2);

        // Clean up non-critical events
        $nonCriticalEvents = OAuthClientEvent::where('occurred_at', '<', $cutoffDate)
                                           ->where('severity', '!=', 'critical')
                                           ->whereNotIn('event_type', [
                                               'security', 'error'
                                           ])
                                           ->count();

        $deletedNonCritical = 0;
        if ($nonCriticalEvents > 0) {
            // Delete in chunks to avoid memory issues
            $chunkSize = 1000;
            do {
                $deletedChunk = OAuthClientEvent::where('occurred_at', '<', $cutoffDate)
                                              ->where('severity', '!=', 'critical')
                                              ->whereNotIn('event_type', [
                                                  'security', 'error'
                                              ])
                                              ->limit($chunkSize)
                                              ->delete();
                
                $deletedNonCritical += $deletedChunk;
                
                if ($deletedChunk > 0) {
                    Log::debug('Cleaned up chunk of non-critical events', [
                        'chunk_size' => $deletedChunk,
                        'total_so_far' => $deletedNonCritical
                    ]);
                }

            } while ($deletedChunk > 0);
        }

        // Clean up very old critical events
        $oldCriticalEvents = OAuthClientEvent::where('occurred_at', '<', $criticalCutoffDate)
                                           ->count();

        $deletedCritical = 0;
        if ($oldCriticalEvents > 0) {
            $deletedCritical = OAuthClientEvent::where('occurred_at', '<', $criticalCutoffDate)
                                             ->delete();
        }

        $totalEventsDeleted = $deletedNonCritical + $deletedCritical;

        Log::info('Cleaned up events', [
            'non_critical_events' => $deletedNonCritical,
            'critical_events' => $deletedCritical,
            'total_events' => $totalEventsDeleted
        ]);

        return $totalEventsDeleted;
    }

    /**
     * Clean up old usage records
     */
    private function cleanupOldUsageRecords(): int
    {
        // Keep usage records for a longer period (1 year)
        $usageRetentionDays = max($this->retentionDays * 4, 365);
        $cutoffDate = now()->subDays($usageRetentionDays);
        
        Log::info('Starting usage records cleanup', [
            'cutoff_date' => $cutoffDate->toDateString(),
            'retention_days' => $usageRetentionDays
        ]);

        $oldUsageRecords = OAuthClientUsage::where('date', '<', $cutoffDate->toDateString())
                                         ->count();

        $deletedUsage = 0;
        if ($oldUsageRecords > 0) {
            $deletedUsage = OAuthClientUsage::where('date', '<', $cutoffDate->toDateString())
                                          ->delete();

            Log::info('Cleaned up old usage records', [
                'count' => $deletedUsage,
                'cutoff_date' => $cutoffDate->toDateString()
            ]);
        }

        return $deletedUsage;
    }

    /**
     * Clean up orphaned records
     */
    private function cleanupOrphanedRecords(): int
    {
        $totalCleaned = 0;

        // Clean up events for non-existent clients
        $orphanedEvents = OAuthClientEvent::whereNotIn('client_id', 
            function($query) {
                $query->select('id')->from('oauth_clients');
            }
        )->count();

        if ($orphanedEvents > 0) {
            $deletedEvents = OAuthClientEvent::whereNotIn('client_id', 
                function($query) {
                    $query->select('id')->from('oauth_clients');
                }
            )->delete();

            Log::info('Cleaned up orphaned events', [
                'count' => $deletedEvents
            ]);

            $totalCleaned += $deletedEvents;
        }

        // Clean up usage records for non-existent clients
        $orphanedUsage = OAuthClientUsage::whereNotIn('client_id', 
            function($query) {
                $query->select('id')->from('oauth_clients');
            }
        )->count();

        if ($orphanedUsage > 0) {
            $deletedUsage = OAuthClientUsage::whereNotIn('client_id', 
                function($query) {
                    $query->select('id')->from('oauth_clients');
                }
            )->delete();

            Log::info('Cleaned up orphaned usage records', [
                'count' => $deletedUsage
            ]);

            $totalCleaned += $deletedUsage;
        }

        return $totalCleaned;
    }

    /**
     * Get cleanup statistics
     */
    private function getCleanupStats(): array
    {
        $now = now();
        $oldCutoff = $now->copy()->subDays($this->retentionDays);

        return [
            'expired_tokens' => OAuthAccessToken::where('expires_at', '<', $now)->count(),
            'expired_codes' => OAuthAuthorizationCode::where('expires_at', '<', $now)->count(),
            'old_events' => OAuthClientEvent::where('occurred_at', '<', $oldCutoff)->count(),
            'old_usage_records' => OAuthClientUsage::where('date', '<', $oldCutoff->toDateString())->count(),
        ];
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('OAuth cleanup job failed permanently', [
            'cleanup_types' => $this->cleanupTypes,
            'retention_days' => $this->retentionDays,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }

    /**
     * Get the tags that should be assigned to the job
     */
    public function tags(): array
    {
        return [
            'oauth',
            'cleanup',
            'maintenance',
            "retention:{$this->retentionDays}days"
        ];
    }
}
