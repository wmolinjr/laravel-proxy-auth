<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OAuthNotification extends Model
{
    use HasFactory;

    protected $table = 'oauth_notifications';

    protected $fillable = [
        'oauth_client_id',
        'alert_rule_id',
        'type',
        'title',
        'message',
        'data',
        'channels_sent',
        'recipients',
        'status',
        'sent_at',
        'acknowledged_at',
        'acknowledged_by',
        'acknowledgment_note',
    ];

    protected $casts = [
        'data' => 'array',
        'channels_sent' => 'array',
        'recipients' => 'array',
        'sent_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    // Relationships
    public function oauthClient(): BelongsTo
    {
        return $this->belongsTo(OAuthClient::class, 'oauth_client_id');
    }

    public function alertRule(): BelongsTo
    {
        return $this->belongsTo(OAuthAlertRule::class, 'alert_rule_id');
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    public function scopeAcknowledged($query)
    {
        return $query->whereNotNull('acknowledged_at');
    }

    public function scopeUnacknowledged($query)
    {
        return $query->whereNull('acknowledged_at');
    }

    public function scopeCritical($query)
    {
        return $query->where('type', 'critical');
    }

    public function scopeByClient($query, $clientId)
    {
        return $query->where('oauth_client_id', $clientId);
    }

    // Helper methods
    public function acknowledge(User $user, string $note = null): void
    {
        $this->update([
            'acknowledged_at' => now(),
            'acknowledged_by' => $user->id,
            'acknowledgment_note' => $note,
        ]);
    }

    public function markAsSent(array $channels = []): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
            'channels_sent' => array_merge($this->channels_sent ?? [], $channels),
        ]);
    }

    public function markAsFailed(): void
    {
        $this->update(['status' => 'failed']);
    }

    public function isAcknowledged(): bool
    {
        return $this->acknowledged_at !== null;
    }

    public function isCritical(): bool
    {
        return $this->type === 'critical';
    }

    public function getTypeColorAttribute(): string
    {
        return match ($this->type) {
            'critical' => 'red',
            'alert' => 'orange',
            'warning' => 'yellow',
            'info' => 'blue',
            default => 'gray',
        };
    }

    public function getFormattedTitleAttribute(): string
    {
        $client = $this->oauthClient;
        $clientName = $client ? $client->name : 'Unknown Client';
        
        return "[{$clientName}] {$this->title}";
    }

    // Static factory methods
    public static function createHealthCheckAlert(
        OAuthClient $client,
        int $consecutiveFailures,
        ?OAuthAlertRule $rule = null
    ): self {
        return static::create([
            'oauth_client_id' => $client->id,
            'alert_rule_id' => $rule?->id,
            'type' => 'critical',
            'title' => 'Health Check Failure',
            'message' => "Client '{$client->name}' health check has failed {$consecutiveFailures} consecutive times.",
            'data' => [
                'consecutive_failures' => $consecutiveFailures,
                'health_check_url' => $client->health_check_url,
                'last_success' => $client->last_health_check_at?->toISOString(),
            ],
            'recipients' => $rule?->recipients ?? [],
            'status' => 'pending',
        ]);
    }

    public static function createHighErrorRateAlert(
        OAuthClient $client,
        float $errorRate,
        ?OAuthAlertRule $rule = null
    ): self {
        return static::create([
            'oauth_client_id' => $client->id,
            'alert_rule_id' => $rule?->id,
            'type' => 'alert',
            'title' => 'High Error Rate Detected',
            'message' => "Client '{$client->name}' error rate has reached {$errorRate}%.",
            'data' => [
                'error_rate_percent' => $errorRate,
                'threshold' => $rule?->conditions[0]['threshold'] ?? 10,
                'measurement_period' => '15 minutes',
            ],
            'recipients' => $rule?->recipients ?? [],
            'status' => 'pending',
        ]);
    }

    public static function createMaintenanceModeAlert(
        OAuthClient $client,
        bool $entering,
        ?string $reason = null,
        ?OAuthAlertRule $rule = null
    ): self {
        $action = $entering ? 'entered' : 'exited';
        $message = "Client '{$client->name}' has {$action} maintenance mode.";
        
        if ($entering && $reason) {
            $message .= " Reason: {$reason}";
        }

        return static::create([
            'oauth_client_id' => $client->id,
            'alert_rule_id' => $rule?->id,
            'type' => 'info',
            'title' => 'Maintenance Mode ' . ucfirst($action),
            'message' => $message,
            'data' => [
                'maintenance_mode' => $entering,
                'reason' => $reason,
                'timestamp' => now()->toISOString(),
            ],
            'recipients' => $rule?->recipients ?? [],
            'status' => 'pending',
        ]);
    }
}