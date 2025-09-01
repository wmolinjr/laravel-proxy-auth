<?php

namespace App\Models\Admin;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class AuditLog extends Model
{
    public $timestamps = false; // Only has created_at

    protected $fillable = [
        'user_id',
        'event_type',
        'entity_type',
        'entity_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Get the user that performed the action
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for filtering by event type
     */
    public function scopeEventType(Builder $query, string $eventType): Builder
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope for filtering by entity
     */
    public function scopeEntity(Builder $query, string $entityType, string $entityId = null): Builder
    {
        $query->where('entity_type', $entityType);
        
        if ($entityId) {
            $query->where('entity_id', $entityId);
        }
        
        return $query;
    }

    /**
     * Scope for filtering by date range
     */
    public function scopeDateRange(Builder $query, $startDate, $endDate = null): Builder
    {
        if ($endDate) {
            return $query->whereBetween('created_at', [$startDate, $endDate]);
        }
        
        return $query->whereDate('created_at', '>=', $startDate);
    }

    /**
     * Scope for filtering by user
     */
    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Get formatted event description
     */
    public function getEventDescriptionAttribute(): string
    {
        $descriptions = [
            'created' => 'criou',
            'updated' => 'atualizou',
            'deleted' => 'excluiu',
            'oauth_login' => 'fez login via OAuth',
            'oauth_client_created' => 'criou cliente OAuth',
            'oauth_client_updated' => 'atualizou cliente OAuth',
            'oauth_client_revoked' => 'revogou cliente OAuth',
            'oauth_token_issued' => 'emitiu token OAuth',
            'oauth_token_refreshed' => 'renovou token OAuth',
            'user_login' => 'fez login',
            'user_logout' => 'fez logout',
            'password_changed' => 'alterou senha',
            'two_factor_enabled' => 'habilitou 2FA',
            'two_factor_disabled' => 'desabilitou 2FA',
        ];

        return $descriptions[$this->event_type] ?? $this->event_type;
    }

    /**
     * Get entity display name
     */
    public function getEntityDisplayNameAttribute(): string
    {
        $entityNames = [
            'User' => 'Usuário',
            'OAuthClient' => 'Cliente OAuth',
            'OAuthAccessToken' => 'Token de Acesso',
            'SystemSetting' => 'Configuração do Sistema',
        ];

        return $entityNames[$this->entity_type] ?? $this->entity_type;
    }

    /**
     * Static method to log events
     */
    public static function logEvent(
        string $eventType,
        ?string $entityType = null,
        ?string $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null
    ): static {
        return static::create([
            'user_id' => auth()->id(),
            'event_type' => $eventType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => $metadata,
        ]);
    }

    /**
     * Get changes summary for display
     */
    public function getChangesSummaryAttribute(): array
    {
        if (!$this->old_values || !$this->new_values) {
            return [];
        }

        $changes = [];
        foreach ($this->new_values as $key => $newValue) {
            $oldValue = $this->old_values[$key] ?? null;
            if ($oldValue !== $newValue) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return $changes;
    }
}