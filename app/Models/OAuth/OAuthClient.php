<?php

namespace App\Models\OAuth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OAuthClient extends Model
{
    protected $table = 'oauth_clients';
    
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'secret',
        'redirect',
        'personal_access_client',
        'password_client',
        'revoked',
    ];

    protected $casts = [
        'personal_access_client' => 'boolean',
        'password_client' => 'boolean',
        'revoked' => 'boolean',
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
     * Get redirect URIs as array
     */
    public function getRedirectUris(): array
    {
        return array_filter(explode(',', $this->redirect));
    }

    /**
     * Set redirect URIs from array
     */
    public function setRedirectUris(array $uris): void
    {
        $this->redirect = implode(',', $uris);
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
}