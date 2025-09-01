<?php

namespace App\Models\OAuth;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OAuthAuthorizationCode extends Model
{
    protected $table = 'oauth_authorization_codes';
    
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'client_id',
        'scopes',
        'revoked',
        'expires_at',
    ];

    protected $casts = [
        'revoked' => 'boolean',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the user that owns the authorization code
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the client that owns the authorization code
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(OAuthClient::class, 'client_id');
    }

    /**
     * Get scopes as array
     */
    public function getScopes(): array
    {
        return $this->scopes ? explode(' ', $this->scopes) : [];
    }

    /**
     * Set scopes from array
     */
    public function setScopes(array $scopes): void
    {
        $this->scopes = implode(' ', $scopes);
    }

    /**
     * Check if authorization code is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if authorization code is valid (not revoked and not expired)
     */
    public function isValid(): bool
    {
        return !$this->revoked && !$this->isExpired();
    }

    /**
     * Revoke this authorization code
     */
    public function revoke(): bool
    {
        $this->revoked = true;
        return $this->save();
    }

    /**
     * Scope to get only valid authorization codes
     */
    public function scopeValid($query)
    {
        return $query->where('revoked', false)
                    ->where('expires_at', '>', now());
    }

    /**
     * Scope to get expired authorization codes
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }
}