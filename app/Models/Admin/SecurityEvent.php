<?php

namespace App\Models\Admin;

use App\Models\User;
use App\Models\OAuth\OAuthClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class SecurityEvent extends Model
{
    protected $fillable = [
        'event_type',
        'severity',
        'client_id',
        'user_id',
        'ip_address',
        'country_code',
        'user_agent',
        'details',
        'is_resolved',
        'resolved_at',
        'resolved_by',
        'resolution_notes',
    ];

    protected $casts = [
        'details' => 'array',
        'is_resolved' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    /**
     * Get the user associated with this security event
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the OAuth client associated with this security event
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(OAuthClient::class, 'client_id');
    }

    /**
     * Get the user who resolved this security event
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Scope for filtering by severity
     */
    public function scopeSeverity(Builder $query, string $severity): Builder
    {
        return $query->where('severity', $severity);
    }

    /**
     * Scope for unresolved events
     */
    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->where('is_resolved', false);
    }

    /**
     * Scope for resolved events
     */
    public function scopeResolved(Builder $query): Builder
    {
        return $query->where('is_resolved', true);
    }

    /**
     * Scope for high severity events
     */
    public function scopeHighSeverity(Builder $query): Builder
    {
        return $query->whereIn('severity', ['high', 'critical']);
    }

    /**
     * Scope for filtering by event type
     */
    public function scopeEventType(Builder $query, string $eventType): Builder
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope for filtering by IP address
     */
    public function scopeByIp(Builder $query, string $ipAddress): Builder
    {
        return $query->where('ip_address', $ipAddress);
    }

    /**
     * Scope for recent events
     */
    public function scopeRecent(Builder $query, int $hours = 24): Builder
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    /**
     * Mark this event as resolved
     */
    public function resolve(string $notes = '', ?int $resolvedBy = null): bool
    {
        $this->is_resolved = true;
        $this->resolved_at = now();
        $this->resolved_by = $resolvedBy ?? auth()->id();
        $this->resolution_notes = $notes;

        $success = $this->save();

        if ($success) {
            // Log the resolution
            AuditLog::logEvent(
                'security_event_resolved',
                'SecurityEvent',
                $this->id,
                null,
                ['resolution_notes' => $notes]
            );
        }

        return $success;
    }

    /**
     * Get severity color for UI
     */
    public function getSeverityColorAttribute(): string
    {
        return match($this->severity) {
            'low' => 'text-green-600 bg-green-100',
            'medium' => 'text-yellow-600 bg-yellow-100',
            'high' => 'text-orange-600 bg-orange-100',
            'critical' => 'text-red-600 bg-red-100',
            default => 'text-gray-600 bg-gray-100',
        };
    }

    /**
     * Get event description for display
     */
    public function getEventDescriptionAttribute(): string
    {
        $descriptions = [
            'failed_login' => 'Tentativa de login falhou',
            'suspicious_activity' => 'Atividade suspeita detectada',
            'rate_limit_exceeded' => 'Limite de taxa excedido',
            'invalid_client_request' => 'Solicitação de cliente inválida',
            'token_abuse' => 'Abuso de token detectado',
            'brute_force_attempt' => 'Tentativa de força bruta',
            'geo_anomaly' => 'Login de localização suspeita',
            'multiple_failed_2fa' => 'Múltiplas falhas de 2FA',
            'account_lockout' => 'Conta bloqueada por segurança',
            'password_spray_attack' => 'Ataque de spray de senha',
        ];

        return $descriptions[$this->event_type] ?? $this->event_type;
    }

    /**
     * Static method to log a security event
     */
    public static function logEvent(
        string $eventType,
        string $severity = 'medium',
        array $details = [],
        ?string $clientId = null,
        ?int $userId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): static {
        return static::create([
            'event_type' => $eventType,
            'severity' => $severity,
            'client_id' => $clientId,
            'user_id' => $userId,
            'ip_address' => $ipAddress ?? request()->ip(),
            'user_agent' => $userAgent ?? request()->userAgent(),
            'details' => $details,
            'country_code' => static::getCountryFromIp($ipAddress ?? request()->ip()),
        ]);
    }

    /**
     * Get country code from IP address (placeholder implementation)
     */
    private static function getCountryFromIp(string $ip): ?string
    {
        // This would integrate with a GeoIP service like MaxMind
        // For now, return null or implement a simple lookup
        return null;
    }

    /**
     * Get security event statistics
     */
    public static function getStatistics(int $days = 30): array
    {
        $since = now()->subDays($days);

        return [
            'total_events' => static::where('created_at', '>=', $since)->count(),
            'unresolved_events' => static::unresolved()->where('created_at', '>=', $since)->count(),
            'high_severity_events' => static::highSeverity()->where('created_at', '>=', $since)->count(),
            'events_by_type' => static::where('created_at', '>=', $since)
                ->selectRaw('event_type, count(*) as count')
                ->groupBy('event_type')
                ->pluck('count', 'event_type')
                ->toArray(),
            'events_by_severity' => static::where('created_at', '>=', $since)
                ->selectRaw('severity, count(*) as count')
                ->groupBy('severity')
                ->pluck('count', 'severity')
                ->toArray(),
        ];
    }
}