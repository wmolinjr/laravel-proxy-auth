<?php

namespace App\Models\Admin;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Crypt;

class SystemSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'is_encrypted',
        'description',
        'category',
        'is_public',
        'updated_by',
    ];

    protected $casts = [
        'value' => 'array',
        'is_encrypted' => 'boolean',
        'is_public' => 'boolean',
    ];

    /**
     * Get the user who last updated this setting
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope for filtering by category
     */
    public function scopeCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    /**
     * Scope for public settings
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    /**
     * Get setting value with automatic decryption
     */
    public function getValueAttribute($value): mixed
    {
        $decoded = json_decode($value, true);
        
        if ($this->is_encrypted && $decoded) {
            try {
                return Crypt::decrypt($decoded);
            } catch (\Exception $e) {
                \Log::error('Failed to decrypt setting', [
                    'key' => $this->key,
                    'error' => $e->getMessage()
                ]);
                return null;
            }
        }
        
        return $decoded;
    }

    /**
     * Set setting value with automatic encryption
     */
    public function setValueAttribute($value): void
    {
        if ($this->is_encrypted) {
            $value = Crypt::encrypt($value);
        }
        
        $this->attributes['value'] = json_encode($value);
    }

    /**
     * Static method to get a setting value
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    /**
     * Static method to set a setting value
     */
    public static function set(string $key, mixed $value, array $options = []): static
    {
        $setting = static::updateOrCreate(
            ['key' => $key],
            array_merge([
                'value' => $value,
                'updated_by' => auth()->id(),
            ], $options)
        );

        // Log the change
        AuditLog::logEvent(
            'system_setting_updated',
            'SystemSetting',
            $setting->id,
            $setting->getOriginal(),
            $setting->getAttributes()
        );

        return $setting;
    }

    /**
     * Get all settings by category
     */
    public static function getByCategory(string $category): array
    {
        return static::category($category)
            ->pluck('value', 'key')
            ->toArray();
    }

    /**
     * Get OAuth configuration settings
     */
    public static function getOAuthConfig(): array
    {
        return static::getByCategory('oauth');
    }

    /**
     * Get security settings
     */
    public static function getSecurityConfig(): array
    {
        return static::getByCategory('security');
    }

    /**
     * Get general application settings
     */
    public static function getGeneralConfig(): array
    {
        return static::getByCategory('general');
    }

    /**
     * Get public settings (can be accessed by non-admin users)
     */
    public static function getPublicSettings(): array
    {
        return static::public()
            ->pluck('value', 'key')
            ->toArray();
    }

    /**
     * Bootstrap default settings
     */
    public static function initializeDefaults(): void
    {
        $defaults = [
            // General settings
            'app.name' => [
                'value' => 'WMJ Identity Provider',
                'category' => 'general',
                'description' => 'Nome da aplicação',
                'is_public' => true,
            ],
            'app.description' => [
                'value' => 'Sistema de Autenticação Centralizado',
                'category' => 'general',
                'description' => 'Descrição da aplicação',
                'is_public' => true,
            ],
            
            // OAuth settings
            'oauth.default_token_lifetime' => [
                'value' => 3600, // 1 hour
                'category' => 'oauth',
                'description' => 'Duração padrão dos tokens de acesso (segundos)',
            ],
            'oauth.max_refresh_token_lifetime' => [
                'value' => 2592000, // 30 days
                'category' => 'oauth',
                'description' => 'Duração máxima dos refresh tokens (segundos)',
            ],
            
            // Security settings
            'security.max_login_attempts' => [
                'value' => 5,
                'category' => 'security',
                'description' => 'Número máximo de tentativas de login',
            ],
            'security.lockout_duration' => [
                'value' => 900, // 15 minutes
                'category' => 'security',
                'description' => 'Duração do bloqueio após tentativas falharam (segundos)',
            ],
            'security.require_2fa_for_admins' => [
                'value' => false,
                'category' => 'security',
                'description' => 'Exigir 2FA para usuários administradores',
            ],
        ];

        foreach ($defaults as $key => $config) {
            if (!static::where('key', $key)->exists()) {
                static::create(array_merge(['key' => $key], $config));
            }
        }
    }
}