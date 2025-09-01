<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OAuth\OAuthServerService;
use Illuminate\Http\JsonResponse;
use League\OAuth2\Server\Exception\OAuthServerException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpMessageFactory;

class UserInfoController extends Controller
{
    protected OAuthServerService $oauthServer;
    protected PsrHttpMessageFactory $psrFactory;

    public function __construct(OAuthServerService $oauthServer)
    {
        $this->oauthServer = $oauthServer;
        
        $psr17Factory = new Psr17Factory();
        $this->psrFactory = new PsrHttpMessageFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);
    }

    /**
     * Get user info for OIDC
     * Supports both GET and POST requests as per OIDC spec
     */
    public function userInfo(): JsonResponse
    {
        try {
            // Convert Laravel request to PSR-7
            $serverRequest = $this->psrFactory->createRequest(request());

            // Validate the access token
            $request = $this->oauthServer->getResourceServer()
                ->validateAuthenticatedRequest($serverRequest);

            // Extract user ID and scopes from validated request
            $userId = $request->getAttribute('oauth_user_id');
            $scopes = $request->getAttribute('oauth_scopes') ?? [];

            // Find user
            $user = User::find($userId);
            
            if (!$user) {
                \Log::warning('UserInfo requested for non-existent user', [
                    'user_id' => $userId,
                    'scopes' => $scopes,
                ]);

                return response()->json([
                    'error' => 'invalid_token',
                    'error_description' => 'User not found',
                ], 404);
            }

            // Build claims based on scopes
            $claims = $this->buildClaims($user, $scopes);

            \Log::info('UserInfo provided successfully', [
                'user_id' => $userId,
                'scopes' => $scopes,
                'claims_count' => count($claims),
            ]);

            return response()->json($claims)
                ->header('Cache-Control', 'no-store')
                ->header('Pragma', 'no-cache');

        } catch (OAuthServerException $exception) {
            \Log::warning('UserInfo OAuth error', [
                'error' => $exception->getErrorType(),
                'message' => $exception->getMessage(),
                'status' => $exception->getHttpStatusCode(),
            ]);

            return response()->json([
                'error' => $exception->getErrorType(),
                'error_description' => $exception->getMessage(),
            ], $exception->getHttpStatusCode())
                ->header('Cache-Control', 'no-store')
                ->header('Pragma', 'no-cache');

        } catch (\Exception $exception) {
            \Log::error('UserInfo system error', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'server_error',
                'error_description' => 'Internal server error occurred',
            ], 500)
                ->header('Cache-Control', 'no-store')
                ->header('Pragma', 'no-cache');
        }
    }

    /**
     * Build user claims based on requested scopes
     */
    protected function buildClaims(User $user, array $scopes): array
    {
        // Start with mandatory 'sub' claim
        $claims = [
            'sub' => (string) $user->id,
        ];

        // Add profile claims if profile scope is requested
        if (in_array('profile', $scopes)) {
            $claims = array_merge($claims, [
                'name' => $user->name,
                'preferred_username' => $user->name,
                'updated_at' => $user->updated_at->timestamp,
            ]);
        }

        // Add email claims if email scope is requested
        if (in_array('email', $scopes)) {
            $claims = array_merge($claims, [
                'email' => $user->email,
                'email_verified' => !is_null($user->email_verified_at),
            ]);
        }

        // Add additional custom claims if needed
        $claims = $this->addCustomClaims($claims, $user, $scopes);

        return $claims;
    }

    /**
     * Add custom claims based on your application needs
     */
    protected function addCustomClaims(array $claims, User $user, array $scopes): array
    {
        // Example: Add role information if user has roles
        if (method_exists($user, 'getRoleNames')) {
            $roles = $user->getRoleNames()->toArray();
            if (!empty($roles)) {
                $claims['roles'] = $roles;
            }
        }

        // Example: Add department if available
        if (isset($user->department)) {
            $claims['department'] = $user->department;
        }

        // Example: Add custom WMJ specific claims
        $claims['provider'] = 'WMJ Identity Provider';
        $claims['iss'] = config('oauth.issuer');

        return $claims;
    }

    /**
     * Alternative method for token introspection (RFC 7662)
     * This could be used by resource servers to validate tokens
     */
    public function introspect(): JsonResponse
    {
        // This is an optional endpoint for token introspection
        // Not part of OIDC core but useful for resource servers
        
        return response()->json([
            'error' => 'not_implemented',
            'error_description' => 'Token introspection not yet implemented',
        ], 501);
    }
}