<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OAuthAlertRule extends Model
{
    use HasFactory;

    protected $table = 'oauth_alert_rules';

    protected $fillable = [
        'name',
        'description',
        'trigger_type',
        'conditions',
        'notification_channels',
        'recipients',
        'is_active',
        'cooldown_minutes',
        'last_triggered_at',
    ];

    protected $casts = [
        'conditions' => 'array',
        'notification_channels' => 'array',
        'recipients' => 'array',
        'is_active' => 'boolean',
        'last_triggered_at' => 'datetime',
    ];

    // Relationships
    public function notifications(): HasMany
    {
        return $this->hasMany(OAuthNotification::class, 'alert_rule_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByTriggerType($query, string $triggerType)
    {
        return $query->where('trigger_type', $triggerType);
    }

    // Helper methods
    public function canTrigger(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if (!$this->last_triggered_at) {
            return true;
        }

        return $this->last_triggered_at->addMinutes($this->cooldown_minutes)->isPast();
    }

    public function markTriggered(): void
    {
        $this->update(['last_triggered_at' => now()]);
    }

    public function evaluateConditions(array $data): bool
    {
        foreach ($this->conditions as $condition) {
            $field = $condition['field'] ?? null;
            $operator = $condition['operator'] ?? '>';
            $threshold = $condition['threshold'] ?? 0;

            if (!isset($data[$field])) {
                continue;
            }

            $value = $data[$field];

            $result = match ($operator) {
                '>' => $value > $threshold,
                '>=' => $value >= $threshold,
                '<' => $value < $threshold,
                '<=' => $value <= $threshold,
                '==' => $value == $threshold,
                '!=' => $value != $threshold,
                'contains' => str_contains(strtolower($value), strtolower($threshold)),
                'in' => in_array($value, (array) $threshold),
                default => false,
            };

            if (!$result) {
                return false;
            }
        }

        return true;
    }

    // Static helper for common rules
    public static function createHealthCheckRule(array $recipients = []): self
    {
        return static::create([
            'name' => 'Health Check Failure Alert',
            'description' => 'Triggered when OAuth client health check fails',
            'trigger_type' => 'health_check_failure',
            'conditions' => [
                ['field' => 'consecutive_failures', 'operator' => '>=', 'threshold' => 3]
            ],
            'notification_channels' => ['email', 'slack'],
            'recipients' => $recipients,
            'cooldown_minutes' => 30,
        ]);
    }

    public static function createHighErrorRateRule(array $recipients = []): self
    {
        return static::create([
            'name' => 'High Error Rate Alert',
            'description' => 'Triggered when error rate exceeds threshold',
            'trigger_type' => 'high_error_rate',
            'conditions' => [
                ['field' => 'error_rate_percent', 'operator' => '>', 'threshold' => 10]
            ],
            'notification_channels' => ['email', 'in_app'],
            'recipients' => $recipients,
            'cooldown_minutes' => 15,
        ]);
    }

    public static function createResponseTimeRule(array $recipients = []): self
    {
        return static::create([
            'name' => 'Slow Response Time Alert',
            'description' => 'Triggered when average response time is too high',
            'trigger_type' => 'response_time_threshold',
            'conditions' => [
                ['field' => 'avg_response_time', 'operator' => '>', 'threshold' => 2000]
            ],
            'notification_channels' => ['email'],
            'recipients' => $recipients,
            'cooldown_minutes' => 60,
        ]);
    }
}