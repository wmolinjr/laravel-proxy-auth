<?php

namespace App\Models\OAuth;

use App\Models\User;
use Database\Factories\OAuth\OAuthClientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OAuthClient extends Model
{
    use HasFactory;

    protected $table = 'oauth_clients';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'identifier',
        'name',
        'description',
        'secret',
        'redirect',
        'redirect_uris',
        'grants',
        'scopes',
        'is_confidential',
        'personal_access_client',
        'password_client',
        'revoked',
        'health_check_url',
        'health_check_interval',
        'health_check_enabled',
        'health_check_failures',
        'last_health_check',
        'health_status',
        'last_error_message',
        'last_activity_at',
        'is_active',
        'maintenance_mode',
        'maintenance_message',
        'environment',
        'tags',
        'contact_email',
        'website_url',
        'max_concurrent_tokens',
        'rate_limit_per_minute',
        'version',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'redirect_uris' => 'array',
        'grants' => 'array',
        'scopes' => 'array',
        'is_confidential' => 'boolean',
        'personal_access_client' => 'boolean',
        'password_client' => 'boolean',
        'revoked' => 'boolean',
        'health_check_enabled' => 'boolean',
        'health_check_interval' => 'integer',
        'last_health_check' => 'datetime',
        'last_activity_at' => 'datetime',
        'is_active' => 'boolean',
        'maintenance_mode' => 'boolean',
        'tags' => 'array',
    ];

    protected $hidden = [
        'secret',
    ];

    /**
     * Get all access tokens for this client
     */
    public function accessTokens(): HasMany
    {
        return $this->hasMany(OAuthAccessToken::class, 'client_id');
    }

    /**
     * Get all authorization codes for this client
     */
    public function authorizationCodes(): HasMany
    {
        return $this->hasMany(OAuthAuthorizationCode::class, 'client_id');
    }

    /**
     * Get all notifications for this client
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(\App\Models\OAuthNotification::class, 'oauth_client_id');
    }

    /**
     * Get all usage statistics for this client
     */
    public function usageStats(): HasMany
    {
        return $this->hasMany(OAuthClientUsage::class, 'client_id');
    }

    /**
     * Get all events for this client
     */
    public function events(): HasMany
    {
        return $this->hasMany(OAuthClientEvent::class, 'client_id');
    }

    /**
     * Get all usage records for this client
     */
    public function usageRecords(): HasMany
    {
        return $this->hasMany(OAuthClientUsage::class, 'client_id');
    }

    /**
     * Get the user who created this client
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this client
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get redirect URIs as array
     */
    public function getRedirectUris(): array
    {
        // Use new redirect_uris column if available, fallback to old redirect column
        if ($this->redirect_uris) {
            return $this->redirect_uris;
        }
        return array_filter(explode(',', $this->redirect ?? ''));
    }

    /**
     * Set redirect URIs from array
     */
    public function setRedirectUris(array $uris): void
    {
        $this->redirect_uris = $uris;
        $this->redirect = implode(',', $uris); // Keep both for compatibility
    }

    /**
     * Check if redirect URI is valid for this client
     */
    public function isValidRedirectUri(string $uri): bool
    {
        return in_array($uri, $this->getRedirectUris());
    }

    /**
     * Scope to get only active clients
     */
    public function scopeActive($query)
    {
        return $query->where('revoked', false);
    }

    /**
     * Scope to get only non-revoked and active clients
     */
    public function scopeEnabled($query)
    {
        return $query->where('revoked', false)->where('is_active', true);
    }

    /**
     * Scope to get clients in maintenance mode
     */
    public function scopeInMaintenance($query)
    {
        return $query->where('maintenance_mode', true);
    }

    /**
     * Scope to get clients by health status
     */
    public function scopeHealthStatus($query, string $status)
    {
        return $query->where('health_status', $status);
    }

    /**
     * Scope to get unhealthy clients
     */
    public function scopeUnhealthy($query)
    {
        return $query->whereIn('health_status', ['unhealthy', 'error']);
    }

    /**
     * Check if client is healthy
     */
    public function isHealthy(): bool
    {
        return $this->health_status === 'healthy';
    }

    /**
     * Check if client needs health check
     */
    public function needsHealthCheck(): bool
    {
        if (!$this->health_check_enabled || !$this->health_check_url) {
            return false;
        }

        if (!$this->last_health_check) {
            return true;
        }

        return $this->last_health_check->addSeconds($this->health_check_interval)->isPast();
    }

    /**
     * Check if client is in maintenance mode
     */
    public function isInMaintenanceMode(): bool
    {
        return $this->maintenance_mode;
    }

    /**
     * Get current usage for today
     */
    public function getTodayUsage(): ?OAuthClientUsage
    {
        return $this->usageRecords()
                    ->where('date', now()->toDateString())
                    ->first();
    }

    /**
     * Get usage for a specific date
     */
    public function getUsageForDate(string $date): ?OAuthClientUsage
    {
        return $this->usageRecords()
                    ->where('date', $date)
                    ->first();
    }

    /**
     * Get recent error events
     */
    public function getRecentErrors(int $hours = 24)
    {
        return $this->events()
                    ->errors()
                    ->recent($hours)
                    ->orderBy('occurred_at', 'desc')
                    ->get();
    }

    /**
     * Get recent security events
     */
    public function getRecentSecurityEvents(int $hours = 24)
    {
        return $this->events()
                    ->security()
                    ->recent($hours)
                    ->orderBy('occurred_at', 'desc')
                    ->get();
    }

    /**
     * Update activity timestamp
     */
    public function touchActivity(): void
    {
        $this->update(['last_activity_at' => now()]);
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): OAuthClientFactory
    {
        return OAuthClientFactory::new();
    }
}
