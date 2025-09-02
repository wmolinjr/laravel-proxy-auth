<?php

namespace App\Models\OAuth;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OAuthClientEvent extends Model
{
    protected $table = 'oauth_client_events';

    protected $fillable = [
        'client_id',
        'user_id',
        'event_type',
        'event_name',
        'description',
        'severity',
        'ip_address',
        'user_agent',
        'metadata',
        'occurred_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];

    // Event types
    public const TYPE_AUTHENTICATION = 'authentication';
    public const TYPE_AUTHORIZATION = 'authorization';
    public const TYPE_TOKEN = 'token';
    public const TYPE_API = 'api';
    public const TYPE_HEALTH = 'health';
    public const TYPE_SECURITY = 'security';
    public const TYPE_SYSTEM = 'system';
    public const TYPE_ERROR = 'error';
    public const TYPE_WARNING = 'warning';
    public const TYPE_INFO = 'info';
    public const TYPE_SUCCESS = 'success';

    // Event severities
    public const SEVERITY_LOW = 'low';
    public const SEVERITY_MEDIUM = 'medium';
    public const SEVERITY_HIGH = 'high';
    public const SEVERITY_CRITICAL = 'critical';

    // Common event names
    public const EVENT_CLIENT_CREATED = 'client_created';
    public const EVENT_CLIENT_UPDATED = 'client_updated';
    public const EVENT_CLIENT_DELETED = 'client_deleted';
    public const EVENT_CLIENT_ACTIVATED = 'client_activated';
    public const EVENT_CLIENT_DEACTIVATED = 'client_deactivated';
    public const EVENT_CLIENT_REVOKED = 'client_revoked';
    public const EVENT_AUTH_REQUEST = 'authorization_request';
    public const EVENT_AUTH_GRANTED = 'authorization_granted';
    public const EVENT_AUTH_DENIED = 'authorization_denied';
    public const EVENT_TOKEN_ISSUED = 'token_issued';
    public const EVENT_TOKEN_REFRESHED = 'token_refreshed';
    public const EVENT_TOKEN_REVOKED = 'token_revoked';
    public const EVENT_API_CALL = 'api_call';
    public const EVENT_HEALTH_CHECK = 'health_check';
    public const EVENT_HEALTH_UP = 'health_up';
    public const EVENT_HEALTH_DOWN = 'health_down';
    public const EVENT_MAINTENANCE_START = 'maintenance_start';
    public const EVENT_MAINTENANCE_END = 'maintenance_end';
    public const EVENT_ERROR_OCCURRED = 'error_occurred';
    public const EVENT_SECURITY_VIOLATION = 'security_violation';

    /**
     * Get the OAuth client that owns this event
     */
    public function oauthClient(): BelongsTo
    {
        return $this->belongsTo(OAuthClient::class, 'client_id', 'id');
    }

    /**
     * Get the user that triggered this event (if applicable)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for specific event type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('event_type', $type);
    }

    /**
     * Scope for specific severity
     */
    public function scopeOfSeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    /**
     * Scope for recent events
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('occurred_at', '>=', now()->subHours($hours));
    }

    /**
     * Scope for errors only
     */
    public function scopeErrors($query)
    {
        return $query->whereIn('event_type', [self::TYPE_ERROR, self::TYPE_WARNING])
                    ->orWhereIn('severity', [self::SEVERITY_HIGH, self::SEVERITY_CRITICAL]);
    }

    /**
     * Scope for security events
     */
    public function scopeSecurity($query)
    {
        return $query->where('event_type', self::TYPE_SECURITY)
                    ->orWhere('event_name', self::EVENT_SECURITY_VIOLATION);
    }

    /**
     * Scope for specific client
     */
    public function scopeForClient($query, string $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    /**
     * Check if event is critical
     */
    public function isCritical(): bool
    {
        return $this->severity === self::SEVERITY_CRITICAL;
    }

    /**
     * Check if event is an error
     */
    public function isError(): bool
    {
        return in_array($this->event_type, [self::TYPE_ERROR, self::TYPE_WARNING]) ||
               in_array($this->severity, [self::SEVERITY_HIGH, self::SEVERITY_CRITICAL]);
    }

    /**
     * Check if event is security-related
     */
    public function isSecurityEvent(): bool
    {
        return $this->event_type === self::TYPE_SECURITY ||
               $this->event_name === self::EVENT_SECURITY_VIOLATION;
    }
}
