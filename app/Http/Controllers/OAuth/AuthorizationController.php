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
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;

class AuthorizationController extends Controller
{
    protected OAuthServerService $oauthServer;
    protected PsrHttpFactory $psrFactory;

    public function __construct(OAuthServerService $oauthServer)
    {
        $this->oauthServer = $oauthServer;
        
        $this->psrFactory = new PsrHttpFactory();
    }

    /**
     * Handle authorization requests
     */
    public function authorize(Request $request)
    {
        try {
            \Log::info('OAuth authorization request started', [
                'query_params' => $request->query(),
                'nonce' => $request->query('nonce', 'NOT_PROVIDED'),
                'state' => $request->query('state', 'NOT_PROVIDED'),
            ]);

            // Store nonce in cache for later retrieval during token exchange
            // We need to associate it with something that will be available during token exchange
            if ($request->has('nonce') && $request->has('state')) {
                $nonce = $request->query('nonce');
                $state = $request->query('state');
                
                \Log::info('OAuth authorization: Storing nonce for later use', [
                    'nonce' => $nonce,
                    'state' => $state,
                ]);
                
                // Store nonce with state as key - mod_auth_openidc will include state in callback
                cache()->put("oauth_nonce_state_{$state}", $nonce, now()->addMinutes(30));
                
                // Also store as latest nonce for simple retrieval during token exchange
                cache()->put('oauth_latest_nonce', $nonce, now()->addMinutes(30));
            }

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
                
                // Criar uma resposta PSR-7 vazia
                $psr7Factory = new \Nyholm\Psr7\Factory\Psr17Factory();
                $psrResponse = $psr7Factory->createResponse();

                // Completar a requisição OAuth
                $finalResponse = $this->oauthServer->getAuthorizationServer()
                    ->completeAuthorizationRequest($authRequest, $psrResponse);

                // Converter PSR-7 response para Laravel response
                return redirect($finalResponse->getHeader('Location')[0] ?? '/')
                    ->withHeaders($finalResponse->getHeaders());
            }

            // Salvar requisição na sessão para usar no approve()
            session(['oauth_auth_request' => serialize($authRequest)]);

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
                'csrf_token' => csrf_token(),
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
            \Log::info('OAuth approve called', [
                'input' => $request->all(),
                'has_session' => session()->has('oauth_auth_request'),
                'user_id' => auth()->id()
            ]);

            $authRequest = unserialize(session('oauth_auth_request'));
            
            if (!$authRequest) {
                \Log::warning('OAuth auth request not found in session');
                return redirect()->route('login')->withErrors(['error' => 'Sessão de autorização expirada']);
            }

            \Log::info('OAuth auth request recovered from session', [
                'client_id' => $authRequest->getClient()->getIdentifier(),
                'redirect_uri' => $authRequest->getRedirectUri(),
                'scopes' => array_map(fn($s) => $s->getIdentifier(), $authRequest->getScopes())
            ]);

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

            // Criar uma resposta PSR-7 vazia
            $psr7Factory = new \Nyholm\Psr7\Factory\Psr17Factory();
            $psrResponse = $psr7Factory->createResponse();

            // Completar a requisição OAuth
            $finalResponse = $this->oauthServer->getAuthorizationServer()
                ->completeAuthorizationRequest($authRequest, $psrResponse);

            \Log::info('OAuth authorization completed', [
                'status' => $finalResponse->getStatusCode(),
                'location' => $finalResponse->getHeader('Location')[0] ?? 'No redirect',
                'headers' => array_keys($finalResponse->getHeaders())
            ]);

            // Converter PSR-7 response para Laravel response
            return redirect($finalResponse->getHeader('Location')[0] ?? '/')
                ->withHeaders($finalResponse->getHeaders());

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