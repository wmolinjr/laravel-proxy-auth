<?php

namespace App\Services;

use App\Models\Admin\AuditLog;
use Illuminate\Http\Request;

class OAuthAuditService
{
    /**
     * OAuth-specific events that should be audited
     */
    private const CRITICAL_EVENTS = [
        'oauth_token_issued',
        'oauth_token_refreshed', 
        'oauth_token_revoked',
        'oauth_authorization_granted',
        'oauth_authorization_denied',
        'oauth_client_authenticated',
        'oauth_client_authentication_failed',
        'oauth_scope_requested',
        'oauth_introspection_performed',
        'oauth_userinfo_accessed',
    ];

    /**
     * Log OAuth authorization attempt
     */
    public static function logAuthorizationAttempt(
        Request $request,
        string $clientId,
        ?int $userId = null,
        array $scopes = [],
        bool $granted = false
    ): void {
        $eventType = $granted ? 'oauth_authorization_granted' : 'oauth_authorization_denied';
        
        AuditLog::logEvent(
            eventType: $eventType,
            entityType: 'OAuthClient',
            entityId: $clientId,
            metadata: [
                'client_id' => $clientId,
                'user_id' => $userId,
                'scopes' => $scopes,
                'response_type' => $request->input('response_type'),
                'redirect_uri' => $request->input('redirect_uri'),
                'state' => $request->input('state') ? 'present' : 'missing',
                'code_challenge' => $request->input('code_challenge') ? 'present' : 'missing',
                'code_challenge_method' => $request->input('code_challenge_method'),
                'nonce' => $request->input('nonce') ? 'present' : 'missing',
            ]
        );
    }

    /**
     * Log token issuance
     */
    public static function logTokenIssued(
        string $clientId,
        ?int $userId = null,
        string $grantType = 'authorization_code',
        array $scopes = [],
        string $tokenId = null
    ): void {
        AuditLog::logEvent(
            eventType: 'oauth_token_issued',
            entityType: 'OAuthAccessToken',
            entityId: $tokenId,
            metadata: [
                'client_id' => $clientId,
                'user_id' => $userId,
                'grant_type' => $grantType,
                'scopes' => $scopes,
                'token_type' => 'Bearer',
            ]
        );
    }

    /**
     * Log token refresh
     */
    public static function logTokenRefreshed(
        string $clientId,
        ?int $userId = null,
        array $scopes = [],
        string $tokenId = null
    ): void {
        AuditLog::logEvent(
            eventType: 'oauth_token_refreshed',
            entityType: 'OAuthAccessToken',
            entityId: $tokenId,
            metadata: [
                'client_id' => $clientId,
                'user_id' => $userId,
                'scopes' => $scopes,
            ]
        );
    }

    /**
     * Log client authentication attempt
     */
    public static function logClientAuthentication(
        string $clientId,
        bool $successful = true,
        ?string $method = null
    ): void {
        $eventType = $successful ? 'oauth_client_authenticated' : 'oauth_client_authentication_failed';
        
        AuditLog::logEvent(
            eventType: $eventType,
            entityType: 'OAuthClient',
            entityId: $clientId,
            metadata: [
                'client_id' => $clientId,
                'authentication_method' => $method,
                'ip_address' => request()->ip(),
            ]
        );
    }

    /**
     * Log scope access
     */
    public static function logScopeAccess(
        string $clientId,
        ?int $userId = null,
        array $scopes = [],
        string $endpoint = 'userinfo'
    ): void {
        AuditLog::logEvent(
            eventType: 'oauth_scope_accessed',
            entityType: 'OAuthClient',
            entityId: $clientId,
            metadata: [
                'client_id' => $clientId,
                'user_id' => $userId,
                'scopes' => $scopes,
                'endpoint' => $endpoint,
            ]
        );
    }

    /**
     * Log token introspection
     */
    public static function logTokenIntrospection(
        string $clientId,
        string $tokenId,
        bool $tokenActive = false
    ): void {
        AuditLog::logEvent(
            eventType: 'oauth_introspection_performed',
            entityType: 'OAuthAccessToken',
            entityId: $tokenId,
            metadata: [
                'client_id' => $clientId,
                'token_active' => $tokenActive,
                'introspected_by' => $clientId,
            ]
        );
    }

    /**
     * Log userinfo access
     */
    public static function logUserInfoAccess(
        string $clientId,
        int $userId,
        array $scopes = []
    ): void {
        AuditLog::logEvent(
            eventType: 'oauth_userinfo_accessed',
            entityType: 'User',
            entityId: (string) $userId,
            metadata: [
                'client_id' => $clientId,
                'user_id' => $userId,
                'scopes' => $scopes,
                'claims_returned' => self::getClaimsForScopes($scopes),
            ]
        );
    }

    /**
     * Log security events
     */
    public static function logSecurityEvent(
        string $eventType,
        array $context = []
    ): void {
        AuditLog::logEvent(
            eventType: "oauth_security_$eventType",
            metadata: array_merge([
                'security_event' => true,
                'timestamp' => now()->toISOString(),
            ], $context)
        );
    }

    /**
     * Log rate limiting events
     */
    public static function logRateLimitHit(
        string $endpoint,
        string $clientId = null,
        int $userId = null
    ): void {
        self::logSecurityEvent('rate_limit_hit', [
            'endpoint' => $endpoint,
            'client_id' => $clientId,
            'user_id' => $userId,
            'ip_address' => request()->ip(),
        ]);
    }

    /**
     * Log suspicious activity
     */
    public static function logSuspiciousActivity(
        string $activityType,
        array $context = []
    ): void {
        self::logSecurityEvent('suspicious_activity', array_merge([
            'activity_type' => $activityType,
            'requires_investigation' => true,
        ], $context));
    }

    /**
     * Get claims that would be returned for given scopes
     */
    private static function getClaimsForScopes(array $scopes): array
    {
        $claims = [];
        
        if (in_array('profile', $scopes)) {
            $claims[] = 'name';
            $claims[] = 'preferred_username';
        }
        
        if (in_array('email', $scopes)) {
            $claims[] = 'email';
            $claims[] = 'email_verified';
        }
        
        if (in_array('openid', $scopes)) {
            $claims[] = 'sub';
        }
        
        return $claims;
    }

    /**
     * Check if event should be audited
     */
    public static function shouldAuditEvent(string $eventType): bool
    {
        return in_array($eventType, self::CRITICAL_EVENTS);
    }

    /**
     * Get audit summary for a time period
     */
    public static function getAuditSummary(int $days = 7): array
    {
        $startDate = now()->subDays($days);
        
        return [
            'total_events' => AuditLog::where('created_at', '>=', $startDate)
                ->where('event_type', 'like', 'oauth_%')
                ->count(),
            
            'tokens_issued' => AuditLog::eventType('oauth_token_issued')
                ->where('created_at', '>=', $startDate)
                ->count(),
                
            'failed_authentications' => AuditLog::eventType('oauth_client_authentication_failed')
                ->where('created_at', '>=', $startDate)
                ->count(),
                
            'security_events' => AuditLog::where('created_at', '>=', $startDate)
                ->where('event_type', 'like', 'oauth_security_%')
                ->count(),
                
            'top_clients' => AuditLog::where('created_at', '>=', $startDate)
                ->where('event_type', 'like', 'oauth_%')
                ->whereNotNull('metadata->client_id')
                ->selectRaw('metadata->>\'$.client_id\' as client_id, count(*) as events')
                ->groupBy('metadata->>\'$.client_id\'')
                ->orderByDesc('events')
                ->limit(10)
                ->pluck('events', 'client_id')
                ->toArray(),
        ];
    }
}