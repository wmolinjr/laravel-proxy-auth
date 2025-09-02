<?php

namespace App\Models\OAuth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OAuthClientUsage extends Model
{
    protected $table = 'oauth_client_usages';

    protected $fillable = [
        'client_id',
        'date',
        'authorization_requests',
        'successful_authorizations',
        'failed_authorizations',
        'token_requests',
        'successful_tokens',
        'failed_tokens',
        'active_users',
        'unique_users',
        'api_calls',
        'bytes_transferred',
        'average_response_time',
        'peak_concurrent_users',
        'error_count',
        'last_activity_at',
    ];

    protected $casts = [
        'date' => 'date',
        'authorization_requests' => 'integer',
        'successful_authorizations' => 'integer',
        'failed_authorizations' => 'integer',
        'token_requests' => 'integer',
        'successful_tokens' => 'integer',
        'failed_tokens' => 'integer',
        'active_users' => 'integer',
        'unique_users' => 'integer',
        'api_calls' => 'integer',
        'bytes_transferred' => 'integer',
        'average_response_time' => 'float',
        'peak_concurrent_users' => 'integer',
        'error_count' => 'integer',
        'last_activity_at' => 'datetime',
    ];

    /**
     * Get the OAuth client that owns this usage record
     */
    public function oauthClient(): BelongsTo
    {
        return $this->belongsTo(OAuthClient::class, 'client_id', 'id');
    }

    /**
     * Calculate authorization success rate
     */
    public function getAuthorizationSuccessRateAttribute(): float
    {
        if ($this->authorization_requests === 0) {
            return 0.0;
        }
        return ($this->successful_authorizations / $this->authorization_requests) * 100;
    }

    /**
     * Calculate token success rate
     */
    public function getTokenSuccessRateAttribute(): float
    {
        if ($this->token_requests === 0) {
            return 0.0;
        }
        return ($this->successful_tokens / $this->token_requests) * 100;
    }

    /**
     * Get total success rate (combined auth + token)
     */
    public function getTotalSuccessRateAttribute(): float
    {
        $totalRequests = $this->authorization_requests + $this->token_requests;
        $totalSuccessful = $this->successful_authorizations + $this->successful_tokens;
        
        if ($totalRequests === 0) {
            return 0.0;
        }
        
        return ($totalSuccessful / $totalRequests) * 100;
    }

    /**
     * Scope for current month
     */
    public function scopeCurrentMonth($query)
    {
        return $query->whereYear('date', now()->year)
                    ->whereMonth('date', now()->month);
    }

    /**
     * Scope for date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    /**
     * Scope for specific client
     */
    public function scopeForClient($query, $clientId)
    {
        return $query->where('client_id', $clientId);
    }
}
