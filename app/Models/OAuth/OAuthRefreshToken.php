<?php

namespace App\Models\OAuth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OAuthRefreshToken extends Model
{
    protected $table = 'oauth_refresh_tokens';
    
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'identifier',
        'access_token_id',
        'revoked',
        'expires_at',
    ];

    protected $casts = [
        'revoked' => 'boolean',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the access token this refresh token belongs to
     */
    public function accessToken(): BelongsTo
    {
        return $this->belongsTo(OAuthAccessToken::class, 'access_token_id');
    }

    /**
     * Check if refresh token is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if refresh token is valid (not revoked and not expired)
     */
    public function isValid(): bool
    {
        return !$this->revoked && !$this->isExpired();
    }

    /**
     * Revoke this refresh token
     */
    public function revoke(): bool
    {
        $this->revoked = true;
        return $this->save();
    }

    /**
     * Scope to get only valid refresh tokens
     */
    public function scopeValid($query)
    {
        return $query->where('revoked', false)
                    ->where('expires_at', '>', now());
    }

    /**
     * Scope to get expired refresh tokens
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }
}