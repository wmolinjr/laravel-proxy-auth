<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Admin\AuditLog;
use App\Models\Admin\SecurityEvent;
use App\Models\OAuth\OAuthAccessToken;
use App\Models\OAuth\OAuthAuthorizationCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'phone',
        'department',
        'job_title',
        'is_active',
        'last_login_at',
        'password_changed_at',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_enabled',
        'preferences',
        'timezone',
        'locale',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'two_factor_recovery_codes' => 'array',
            'two_factor_enabled' => 'boolean',
            'preferences' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * OAuth Relationships
     */
    public function accessTokens(): HasMany
    {
        return $this->hasMany(OAuthAccessToken::class);
    }

    public function authorizationCodes(): HasMany
    {
        return $this->hasMany(OAuthAuthorizationCode::class);
    }

    /**
     * Admin Relationships
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function securityEvents(): HasMany
    {
        return $this->hasMany(SecurityEvent::class);
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->hasRole(['super-admin', 'admin']);
    }

    /**
     * Check if user is super admin
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super-admin');
    }

    /**
     * Get user's active OAuth tokens count
     */
    public function getActiveTokensCountAttribute(): int
    {
        return $this->accessTokens()->valid()->count();
    }

    /**
     * Get user's full name with title if available
     */
    public function getFullNameAttribute(): string
    {
        $name = $this->name;
        if ($this->job_title) {
            $name = "{$this->job_title} {$name}";
        }
        return $name;
    }

    /**
     * Get user's avatar URL or default
     */
    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar) {
            return asset("storage/{$this->avatar}");
        }
        
        // Generate avatar based on initials
        $initials = collect(explode(' ', $this->name))
            ->map(fn($word) => strtoupper(substr($word, 0, 1)))
            ->take(2)
            ->implode('');
            
        return "https://ui-avatars.com/api/?name={$initials}&background=3b82f6&color=ffffff";
    }

    /**
     * Check if user needs to change password
     */
    public function needsPasswordChange(): bool
    {
        if (!$this->password_changed_at) {
            return true; // Never changed password
        }
        
        // Force password change every 90 days for admins
        if ($this->isAdmin()) {
            return $this->password_changed_at->diffInDays(now()) > 90;
        }
        
        return false;
    }

    /**
     * Check if 2FA is required for this user
     */
    public function requires2FA(): bool
    {
        // Require 2FA for admins if system setting is enabled
        if ($this->isAdmin()) {
            return \App\Models\Admin\SystemSetting::get('security.require_2fa_for_admins', false);
        }
        
        return false;
    }

    /**
     * Update last login timestamp
     */
    public function updateLastLogin(): void
    {
        $this->last_login_at = now();
        $this->save(['timestamps' => false]); // Don't update updated_at
        
        // Log login event
        AuditLog::logEvent('user_login', 'User', $this->id);
    }

    /**
     * Get user preferences with defaults
     */
    public function getPreference(string $key, mixed $default = null): mixed
    {
        return data_get($this->preferences, $key, $default);
    }

    /**
     * Set user preference
     */
    public function setPreference(string $key, mixed $value): void
    {
        $preferences = $this->preferences ?? [];
        data_set($preferences, $key, $value);
        $this->preferences = $preferences;
        $this->save();
    }

    /**
     * Scope for active users
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for admin users
     */
    public function scopeAdmins($query)
    {
        return $query->whereHas('roles', function ($q) {
            $q->whereIn('name', ['super-admin', 'admin']);
        });
    }

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        // Log user creation
        static::created(function ($user) {
            AuditLog::logEvent('created', 'User', $user->id, null, $user->toArray());
        });

        // Log user updates
        static::updated(function ($user) {
            if ($user->wasChanged() && !$user->wasChanged('last_login_at')) {
                AuditLog::logEvent(
                    'updated', 
                    'User', 
                    $user->id, 
                    $user->getOriginal(), 
                    $user->getChanges()
                );
            }
        });

        // Log user deletion
        static::deleted(function ($user) {
            AuditLog::logEvent('deleted', 'User', $user->id, $user->toArray());
        });
    }
}
