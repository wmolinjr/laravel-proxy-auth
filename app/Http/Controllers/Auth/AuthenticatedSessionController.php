<?php

namespace App\Http\Controllers\Auth;

use App\Entities\OAuth\UserEntity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\OAuth\OAuthClient;
use App\Services\OAuth\OAuthServerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use League\OAuth2\Server\Exception\OAuthServerException;

class AuthenticatedSessionController extends Controller
{
    protected OAuthServerService $oauthServer;

    public function __construct(OAuthServerService $oauthServer)
    {
        $this->oauthServer = $oauthServer;
    }

    /**
     * Show the login page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // Check if there's a pending OAuth authorization request
        if ($request->session()->has('oauth_auth_request')) {
            \Log::info('Login completed with pending OAuth authorization request', [
                'user_id' => auth()->id(),
                'has_oauth_request' => true
            ]);

            return $this->completeOAuthFlow($request);
        }

        // No OAuth request - proceed with normal login redirect
        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Complete OAuth authorization flow after successful login
     */
    protected function completeOAuthFlow(Request $request)
    {
        try {
            // Recover the stored authorization request
            $authRequest = unserialize($request->session()->get('oauth_auth_request'));
            
            if (!$authRequest) {
                \Log::warning('OAuth auth request not found in session during login completion');
                return redirect()->route('dashboard')->withErrors(['error' => 'Sessão OAuth expirada']);
            }

            \Log::info('Completing OAuth authorization after successful login', [
                'client_id' => $authRequest->getClient()->getIdentifier(),
                'redirect_uri' => $authRequest->getRedirectUri(),
                'user_id' => auth()->id()
            ]);

            $user = auth()->user();
            
            if (!$user) {
                \Log::error('User not authenticated during OAuth completion');
                return redirect()->route('login')->withErrors(['error' => 'Usuário não autenticado']);
            }

            // Set user and auto-approve the authorization
            $authRequest->setUser(new UserEntity($user->id));
            $authRequest->setAuthorizationApproved(true);

            // Clear the OAuth request from session
            $request->session()->forget('oauth_auth_request');

            // Create PSR-7 response and complete the authorization
            $psr7Factory = new \Nyholm\Psr7\Factory\Psr17Factory();
            $psrResponse = $psr7Factory->createResponse();

            $finalResponse = $this->oauthServer->getAuthorizationServer()
                ->completeAuthorizationRequest($authRequest, $psrResponse);

            $redirectUrl = $finalResponse->getHeader('Location')[0] ?? '/';
            
            \Log::info('OAuth authorization completed successfully after login', [
                'status' => $finalResponse->getStatusCode(),
                'redirect_url' => $redirectUrl,
                'user_id' => $user->id
            ]);

            // For OAuth redirects, we need a full page redirect, not AJAX
            // Send JavaScript to redirect the browser
            return response(
                '<script>window.location.href = "' . $redirectUrl . '";</script>',
                200,
                ['Content-Type' => 'text/html']
            );

        } catch (OAuthServerException $exception) {
            \Log::error('OAuth server exception during login completion', [
                'error' => $exception->getErrorType(),
                'message' => $exception->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return redirect()->route('dashboard')->withErrors([
                'error' => 'Erro na autorização OAuth: ' . $exception->getMessage()
            ]);

        } catch (\Exception $exception) {
            \Log::error('System error during OAuth completion after login', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
                'user_id' => auth()->id(),
            ]);

            return redirect()->route('dashboard')->withErrors([
                'error' => 'Erro interno no sistema'
            ]);
        }
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
