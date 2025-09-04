<?php

namespace App\Http\Middleware;

use App\Jobs\LogOAuthMetric;
use App\Models\Admin\OAuthMetric;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

class OAuthMetricsMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): BaseResponse
    {
        $startTime = microtime(true);
        $endpoint = $this->determineEndpoint($request);
        
        $response = $next($request);
        
        // Calculate response time
        $responseTimeMs = (int) round((microtime(true) - $startTime) * 1000);
        
        // Extract OAuth-specific data
        $clientId = $this->extractClientId($request);
        $userId = $this->extractUserId($request);
        $tokenType = $this->extractTokenType($request, $response);
        $scopes = $this->extractScopes($request, $response);
        $errorType = $this->extractErrorType($response);
        
        // Log metrics asynchronously using a proper Job
        LogOAuthMetric::dispatch(
            endpoint: $endpoint,
            responseTimeMs: $responseTimeMs,
            statusCode: $response->getStatusCode(),
            clientId: $clientId,
            userId: $userId,
            tokenType: $tokenType,
            scopes: $scopes,
            errorType: $errorType,
            metadata: $this->extractMetadata($request, $response)
        );
        
        return $response;
    }

    /**
     * Determine the OAuth endpoint from the request
     */
    private function determineEndpoint(Request $request): string
    {
        $path = $request->path();
        
        return match (true) {
            str_contains($path, 'oauth/token') => 'token',
            str_contains($path, 'oauth/authorize') => 'authorize', 
            str_contains($path, 'oauth/userinfo') => 'userinfo',
            str_contains($path, 'oauth/introspect') => 'introspect',
            str_contains($path, '.well-known/openid-configuration') => 'oidc-discovery',
            str_contains($path, '.well-known/jwks.json') => 'jwks',
            default => 'unknown'
        };
    }

    /**
     * Extract client ID from request
     */
    private function extractClientId(Request $request): ?string
    {
        // Check multiple possible locations
        return $request->input('client_id') 
            ?? $request->header('authorization') // For Bearer tokens
            ?? null;
    }

    /**
     * Extract user ID from request/session
     */
    private function extractUserId(Request $request): ?int
    {
        return auth()->id() ?? $request->input('user_id');
    }

    /**
     * Extract token type from request/response
     */
    private function extractTokenType(Request $request, BaseResponse $response): ?string
    {
        // For token endpoint, determine what type was requested/returned
        if (str_contains($request->path(), 'oauth/token')) {
            $grantType = $request->input('grant_type');
            return match ($grantType) {
                'authorization_code' => 'access_token',
                'refresh_token' => 'refresh_token',
                'client_credentials' => 'client_token',
                default => $grantType
            };
        }

        return null;
    }

    /**
     * Extract scopes from request/response
     */
    private function extractScopes(Request $request, BaseResponse $response): ?array
    {
        $scopes = $request->input('scope');
        
        if ($scopes) {
            return is_array($scopes) ? $scopes : explode(' ', $scopes);
        }

        return null;
    }

    /**
     * Extract error type from response
     */
    private function extractErrorType(BaseResponse $response): ?string
    {
        if ($response->getStatusCode() >= 400) {
            $content = $response->getContent();
            
            if ($content && is_string($content)) {
                $data = json_decode($content, true);
                if (isset($data['error'])) {
                    return $data['error'];
                }
            }
            
            // Classify by status code
            return match ($response->getStatusCode()) {
                400 => 'bad_request',
                401 => 'unauthorized',
                403 => 'forbidden',
                404 => 'not_found',
                429 => 'rate_limited',
                500 => 'internal_error',
                502 => 'bad_gateway',
                503 => 'service_unavailable',
                default => 'unknown_error'
            };
        }

        return null;
    }

    /**
     * Extract additional metadata
     */
    private function extractMetadata(Request $request, BaseResponse $response): array
    {
        $metadata = [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'response_size' => strlen($response->getContent() ?? ''),
        ];

        // Add specific metadata based on endpoint
        if (str_contains($request->path(), 'oauth/token')) {
            $metadata['grant_type'] = $request->input('grant_type');
            $metadata['response_type'] = $request->input('response_type');
        }

        if (str_contains($request->path(), 'oauth/authorize')) {
            $metadata['response_type'] = $request->input('response_type');
            $metadata['state'] = $request->input('state') ? 'present' : 'missing';
            $metadata['code_challenge'] = $request->input('code_challenge') ? 'present' : 'missing';
        }

        return $metadata;
    }
}