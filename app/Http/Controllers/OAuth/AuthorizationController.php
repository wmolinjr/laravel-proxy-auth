<?php

namespace App\Http\Controllers\OAuth;

use App\Entities\OAuth\UserEntity;
use App\Http\Controllers\Controller;
use App\Models\OAuth\OAuthClient;
use App\Services\OAuth\OAuthServerService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use League\OAuth2\Server\Exception\OAuthServerException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpMessageFactory;

class AuthorizationController extends Controller
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
     * Handle authorization requests
     */
    public function authorize(Request $request)
    {
        try {
            // Convert Laravel request to PSR-7
            $serverRequest = $this->psrFactory->createRequest($request);

            // Validate authorization request
            $authRequest = $this->oauthServer->getAuthorizationServer()
                ->validateAuthorizationRequest($serverRequest);

            $clientId = $authRequest->getClient()->getIdentifier();
            $client = OAuthClient::find($clientId);
            
            if (!$client || $client->revoked) {
                throw OAuthServerException::invalidClient($serverRequest);
            }

            // Se o usuário não estiver autenticado, redirecionar para login
            if (!auth()->check()) {
                session(['oauth_auth_request' => serialize($authRequest)]);
                
                return redirect()->route('login')->with([
                    'message' => "Faça login para acessar {$client->name}",
                    'oauth_client' => $client->name
                ]);
            }

            $user = auth()->user();
            $scopes = $authRequest->getScopes();

            // Verificar se o usuário já aprovou este cliente anteriormente
            if ($this->userHasApprovedClient($user, $client, $scopes)) {
                // Auto-aprovar se já foi aprovado antes
                $authRequest->setUser(new UserEntity($user->id));
                $authRequest->setAuthorizationApproved(true);
                
                return $this->oauthServer->getAuthorizationServer()
                    ->completeAuthorizationRequest($authRequest, response());
            }

            // Mostrar página de consentimento
            return Inertia::render('OAuth/Authorize', [
                'client' => [
                    'id' => $client->id,
                    'name' => $client->name,
                    'redirect_uri' => $authRequest->getRedirectUri(),
                ],
                'scopes' => $this->formatScopes($scopes),
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ]);

        } catch (OAuthServerException $exception) {
            return $this->handleOAuthException($exception);
        } catch (\Exception $exception) {
            return $this->handleGenericException($exception);
        }
    }

    /**
     * Handle authorization approval/denial
     */
    public function approve(Request $request)
    {
        try {
            $authRequest = unserialize(session('oauth_auth_request'));
            
            if (!$authRequest) {
                return redirect()->route('login')->withErrors(['error' => 'Sessão de autorização expirada']);
            }

            $user = auth()->user();
            
            if (!$user) {
                return redirect()->route('login')->withErrors(['error' => 'Usuário não autenticado']);
            }

            // Definir usuário na requisição
            $authRequest->setUser(new UserEntity($user->id));

            if ($request->input('approve') === 'yes') {
                $authRequest->setAuthorizationApproved(true);
                
                // Salvar aprovação para futuras requisições
                $this->saveUserApproval($user, $authRequest);
            } else {
                $authRequest->setAuthorizationApproved(false);
            }

            session()->forget('oauth_auth_request');

            return $this->oauthServer->getAuthorizationServer()
                ->completeAuthorizationRequest($authRequest, response());

        } catch (OAuthServerException $exception) {
            return $this->handleOAuthException($exception);
        } catch (\Exception $exception) {
            return $this->handleGenericException($exception);
        }
    }

    /**
     * Check if user has previously approved this client
     */
    protected function userHasApprovedClient($user, OAuthClient $client, array $scopes): bool
    {
        // Verificar se existe um token ativo para este usuário e cliente
        $existingToken = $client->accessTokens()
            ->where('user_id', $user->id)
            ->valid()
            ->first();

        if (!$existingToken) {
            return false;
        }

        // Verificar se os escopos solicitados estão incluídos nos escopos aprovados
        $approvedScopes = $existingToken->getScopes();
        $requestedScopes = array_map(fn($scope) => $scope->getIdentifier(), $scopes);

        return empty(array_diff($requestedScopes, $approvedScopes));
    }

    /**
     * Save user approval for future requests
     */
    protected function saveUserApproval($user, $authRequest): void
    {
        // Esta função pode ser expandida para salvar aprovações permanentes
        // Por enquanto, dependemos dos tokens existentes como prova de aprovação
    }

    /**
     * Format scopes for display
     */
    protected function formatScopes(array $scopes): array
    {
        $scopeDescriptions = config('oauth.scopes');
        
        return array_map(function ($scope) use ($scopeDescriptions) {
            $identifier = $scope->getIdentifier();
            return [
                'id' => $identifier,
                'name' => $identifier,
                'description' => $scopeDescriptions[$identifier] ?? $identifier,
            ];
        }, $scopes);
    }

    /**
     * Handle OAuth server exceptions
     */
    protected function handleOAuthException(OAuthServerException $exception)
    {
        \Log::warning('OAuth authorization error', [
            'error' => $exception->getErrorType(),
            'message' => $exception->getMessage(),
            'user_id' => auth()->id(),
        ]);

        if ($exception->hasRedirect()) {
            return redirect($exception->getRedirectUri());
        }

        return Inertia::render('OAuth/Error', [
            'error' => $exception->getErrorType(),
            'error_description' => $exception->getMessage(),
            'status' => $exception->getHttpStatusCode(),
        ]);
    }

    /**
     * Handle generic exceptions
     */
    protected function handleGenericException(\Exception $exception)
    {
        \Log::error('OAuth authorization system error', [
            'error' => $exception->getMessage(),
            'user_id' => auth()->id(),
            'trace' => $exception->getTraceAsString(),
        ]);

        return Inertia::render('OAuth/Error', [
            'error' => 'server_error',
            'error_description' => 'Internal server error occurred',
            'status' => 500,
        ]);
    }
}