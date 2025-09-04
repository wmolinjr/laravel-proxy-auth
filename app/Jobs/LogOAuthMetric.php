<?php

namespace App\Jobs;

use App\Models\Admin\OAuthMetric;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class LogOAuthMetric implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $endpoint,
        public int $responseTimeMs,
        public int $statusCode,
        public ?string $clientId = null,
        public ?int $userId = null,
        public ?string $tokenType = null,
        public ?array $scopes = null,
        public ?string $errorType = null,
        public ?array $metadata = null
    ) {
        $this->onQueue('metrics');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            OAuthMetric::logMetric(
                endpoint: $this->endpoint,
                responseTimeMs: $this->responseTimeMs,
                statusCode: $this->statusCode,
                clientId: $this->clientId,
                userId: $this->userId,
                tokenType: $this->tokenType,
                scopes: $this->scopes,
                errorType: $this->errorType,
                metadata: $this->metadata
            );
        } catch (\Exception $e) {
            // Log but don't fail the queue
            \Log::warning('Failed to log OAuth metrics in job', [
                'error' => $e->getMessage(),
                'endpoint' => $this->endpoint,
                'status_code' => $this->statusCode
            ]);
        }
    }
}