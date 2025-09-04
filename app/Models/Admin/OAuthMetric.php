<?php

namespace App\Models\Admin;

use App\Models\OAuth\OAuthClient;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class OAuthMetric extends Model
{
    protected $table = 'oauth_metrics';
    
    protected $fillable = [
        'endpoint',
        'client_id',
        'user_id',
        'response_time_ms',
        'status_code',
        'token_type',
        'scopes',
        'error_type',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected $casts = [
        'response_time_ms' => 'integer',
        'status_code' => 'integer',
        'scopes' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the client associated with this metric
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(OAuthClient::class, 'client_id', 'identifier');
    }

    /**
     * Get the user associated with this metric
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for filtering by endpoint
     */
    public function scopeEndpoint(Builder $query, string $endpoint): Builder
    {
        return $query->where('endpoint', $endpoint);
    }

    /**
     * Scope for filtering by success (2xx status codes)
     */
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->whereBetween('status_code', [200, 299]);
    }

    /**
     * Scope for filtering by errors (4xx/5xx status codes)
     */
    public function scopeErrors(Builder $query): Builder
    {
        return $query->where('status_code', '>=', 400);
    }

    /**
     * Scope for filtering by slow requests
     */
    public function scopeSlowRequests(Builder $query, int $thresholdMs = 1000): Builder
    {
        return $query->where('response_time_ms', '>', $thresholdMs);
    }

    /**
     * Scope for filtering by client
     */
    public function scopeClient(Builder $query, string $clientId): Builder
    {
        return $query->where('client_id', $clientId);
    }

    /**
     * Scope for filtering by date range
     */
    public function scopeDateRange(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Static method to log OAuth metrics
     */
    public static function logMetric(
        string $endpoint,
        int $responseTimeMs,
        int $statusCode,
        ?string $clientId = null,
        ?int $userId = null,
        ?string $tokenType = null,
        ?array $scopes = null,
        ?string $errorType = null,
        ?array $metadata = null
    ): static {
        return static::create([
            'endpoint' => $endpoint,
            'client_id' => $clientId,
            'user_id' => $userId,
            'response_time_ms' => $responseTimeMs,
            'status_code' => $statusCode,
            'token_type' => $tokenType,
            'scopes' => $scopes,
            'error_type' => $errorType,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => $metadata,
        ]);
    }

    /**
     * Get average response time for an endpoint
     */
    public static function averageResponseTime(string $endpoint, int $days = 7): float
    {
        return static::endpoint($endpoint)
            ->where('created_at', '>=', now()->subDays($days))
            ->avg('response_time_ms') ?? 0.0;
    }

    /**
     * Get success rate for an endpoint
     */
    public static function successRate(string $endpoint, int $days = 7): float
    {
        $total = static::endpoint($endpoint)
            ->where('created_at', '>=', now()->subDays($days))
            ->count();

        if ($total === 0) return 100.0;

        $successful = static::endpoint($endpoint)
            ->successful()
            ->where('created_at', '>=', now()->subDays($days))
            ->count();

        return round(($successful / $total) * 100, 2);
    }

    /**
     * Get request count for an endpoint
     */
    public static function requestCount(string $endpoint, int $days = 7): int
    {
        return static::endpoint($endpoint)
            ->where('created_at', '>=', now()->subDays($days))
            ->count();
    }
}