<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Models\OAuth\OAuthAccessToken;
use App\Services\OAuth\JwtService;
use App\Services\OAuth\OAuthServerService;
use Illuminate\Http\JsonResponse;
use League\OAuth2\Server\Exception\OAuthServerException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;

class TokenController extends Controller
{
    protected OAuthServerService $oauthServer;
    protected JwtService $jwtService;
    protected PsrHttpFactory $psrFactory;

    public function __construct(OAuthServerService $oauthServer, JwtService $jwtService)
    {
        $this->oauthServer = $oauthServer;
        $this->jwtService = $jwtService;
        
        $psr17Factory = new Psr17Factory();
        $this->psrFactory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);
    }

    /**
     * Issue access token
     */
    public function issueToken(): JsonResponse
    {
        try {
            // Convert Laravel request to PSR-7
            $serverRequest = $this->psrFactory->createRequest(request());
            
            // Get PSR-7 response from OAuth server
            $psrResponse = $this->oauthServer->getAuthorizationServer()
                ->respondToAccessTokenRequest($serverRequest, $this->psrFactory->createResponse(response()));

            // Convert PSR-7 response back to array
            $responseBody = json_decode($psrResponse->getBody()->getContents(), true);

            // Check if this is an OIDC request (contains openid scope)
            if ($this->isOidcRequest($serverRequest)) {
                $responseBody = $this->addIdToken($responseBody);
            }

            // Log successful token issuance
            \Log::info('OAuth token issued successfully', [
                'client_id' => $this->extractClientId($serverRequest),
                'grant_type' => $this->extractGrantType($serverRequest),
                'has_id_token' => isset($responseBody['id_token']),
            ]);

            return response()->json($responseBody)
                ->header('Cache-Control', 'no-store')
                ->header('Pragma', 'no-cache');

        } catch (OAuthServerException $exception) {
            \Log::warning('OAuth token error', [
                'error' => $exception->getErrorType(),
                'message' => $exception->getMessage(),
                'client_id' => $this->extractClientId($serverRequest ?? null),
            ]);

            return response()->json([
                'error' => $exception->getErrorType(),
                'error_description' => $exception->getMessage(),
            ], $exception->getHttpStatusCode())
                ->header('Cache-Control', 'no-store')
                ->header('Pragma', 'no-cache');

        } catch (\Exception $exception) {
            \Log::error('OAuth token system error', [
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
     * Check if request is for OIDC (has openid scope)
     */
    protected function isOidcRequest($serverRequest): bool
    {
        $body = $serverRequest->getParsedBody();
        $scope = $body['scope'] ?? '';
        
        return str_contains($scope, 'openid');
    }

    /**
     * Add ID token to OIDC response
     */
    protected function addIdToken(array $responseBody): array
    {
        try {
            // Extract access token ID from JWT
            $accessTokenId = $this->extractTokenIdFromJwt($responseBody['access_token']);
            
            if (!$accessTokenId) {
                return $responseBody;
            }

            // Find access token in database
            $accessToken = OAuthAccessToken::with('user')->find($accessTokenId);
            
            if (!$accessToken || !$accessToken->user) {
                return $responseBody;
            }

            // Get scopes and nonce
            $scopes = $accessToken->getScopes();
            $nonce = request()->input('nonce'); // From authorization request

            // Create ID token
            $idToken = $this->jwtService->createIdToken(
                $accessToken->user,
                $accessToken->client_id,
                $scopes,
                $nonce
            );

            $responseBody['id_token'] = $idToken;

            \Log::info('ID token added to OAuth response', [
                'user_id' => $accessToken->user->id,
                'client_id' => $accessToken->client_id,
                'scopes' => $scopes,
            ]);

        } catch (\Exception $exception) {
            \Log::error('Failed to add ID token', [
                'error' => $exception->getMessage(),
                'access_token_preview' => substr($responseBody['access_token'] ?? '', 0, 50),
            ]);
            
            // Continue without ID token rather than failing the entire request
        }

        return $responseBody;
    }

    /**
     * Extract token ID from JWT access token
     */
    protected function extractTokenIdFromJwt(string $accessToken): ?string
    {
        try {
            $payload = $this->jwtService->verifyToken($accessToken);
            return $payload['jti'] ?? null;
        } catch (\Exception $exception) {
            \Log::warning('Failed to extract token ID from JWT', [
                'error' => $exception->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Extract client ID from request
     */
    protected function extractClientId($serverRequest): ?string
    {
        if (!$serverRequest) {
            return null;
        }

        $body = $serverRequest->getParsedBody();
        return $body['client_id'] ?? null;
    }

    /**
     * Extract grant type from request
     */
    protected function extractGrantType($serverRequest): ?string
    {
        if (!$serverRequest) {
            return null;
        }

        $body = $serverRequest->getParsedBody();
        return $body['grant_type'] ?? null;
    }
}