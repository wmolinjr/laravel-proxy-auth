<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OAuth\OAuthClient;
use App\Models\Admin\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class OAuthClientController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:oauth_clients.view')->only(['index', 'show']);
        $this->middleware('can:oauth_clients.create')->only(['create', 'store']);
        $this->middleware('can:oauth_clients.edit')->only(['edit', 'update']);
        $this->middleware('can:oauth_clients.delete')->only(['destroy']);
        $this->middleware('can:oauth_clients.regenerate_secret')->only(['regenerateSecret']);
    }

    public function index(Request $request): Response
    {
        $query = OAuthClient::withCount(['accessTokens', 'authorizationCodes']);

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('identifier', 'ilike', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->filled('status')) {
            $status = $request->get('status');
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        // Filter by confidential
        if ($request->filled('confidential')) {
            $confidential = $request->get('confidential');
            $query->where('is_confidential', $confidential === 'true');
        }

        // Sorting
        $sortBy = $request->get('sort', 'created_at');
        $sortOrder = $request->get('order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $clients = $query->paginate(15)->through(function ($client) {
            return [
                'id' => $client->id,
                'name' => $client->name,
                'identifier' => $client->identifier,
                'is_active' => $client->is_active,
                'is_confidential' => $client->is_confidential,
                'redirect_uris' => $client->redirect_uris,
                'grants' => $client->grants,
                'scopes' => $client->scopes,
                'access_tokens_count' => $client->access_tokens_count,
                'authorization_codes_count' => $client->authorization_codes_count,
                'created_at' => $client->created_at->format('M d, Y'),
                'updated_at' => $client->updated_at->format('M d, Y'),
            ];
        });

        return Inertia::render('Admin/OAuthClients/Index', [
            'clients' => $clients,
            'filters' => $request->only(['search', 'status', 'confidential', 'sort', 'order']),
            'stats' => [
                'total' => OAuthClient::count(),
                'active' => OAuthClient::where('is_active', true)->count(),
                'confidential' => OAuthClient::where('is_confidential', true)->count(),
                'public' => OAuthClient::where('is_confidential', false)->count(),
            ]
        ]);
    }

    public function create(): Response
    {
        $this->authorize('oauth_clients.create');
        
        return Inertia::render('Admin/OAuthClients/Create', [
            'availableGrants' => [
                'authorization_code' => 'Authorization Code',
                'client_credentials' => 'Client Credentials',
                'refresh_token' => 'Refresh Token',
            ],
            'availableScopes' => [
                'read' => 'Read access',
                'write' => 'Write access',
                'openid' => 'OpenID Connect',
                'profile' => 'User profile',
                'email' => 'Email address',
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('oauth_clients.create');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'redirect_uris' => 'required|array|min:1',
            'redirect_uris.*' => 'required|url',
            'grants' => 'required|array|min:1',
            'grants.*' => 'required|string|in:authorization_code,client_credentials,refresh_token',
            'scopes' => 'nullable|array',
            'scopes.*' => 'required|string|in:read,write,openid,profile,email',
            'is_confidential' => 'required|boolean',
            'is_active' => 'boolean',
        ]);

        // Generate client credentials
        $identifier = 'client_' . Str::random(32);
        $secret = $validated['is_confidential'] ? Str::random(64) : null;

        $client = OAuthClient::create([
            'identifier' => $identifier,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'secret' => $secret ? hash('sha256', $secret) : null,
            'redirect_uris' => $validated['redirect_uris'],
            'grants' => $validated['grants'],
            'scopes' => $validated['scopes'] ?? [],
            'is_confidential' => $validated['is_confidential'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        // Store plain secret in session for one-time display
        if ($secret) {
            session()->flash('client_secret', $secret);
        }

        return redirect()->route('admin.oauth-clients.show', $client)
            ->with('success', 'Cliente OAuth criado com sucesso.');
    }

    public function show(OAuthClient $oauthClient): Response
    {
        $this->authorize('oauth_clients.view');

        $oauthClient->loadCount(['accessTokens', 'authorizationCodes']);

        return Inertia::render('Admin/OAuthClients/Show', [
            'client' => [
                'id' => $oauthClient->id,
                'identifier' => $oauthClient->identifier,
                'name' => $oauthClient->name,
                'description' => $oauthClient->description,
                'redirect_uris' => $oauthClient->redirect_uris,
                'grants' => $oauthClient->grants,
                'scopes' => $oauthClient->scopes,
                'is_confidential' => $oauthClient->is_confidential,
                'is_active' => $oauthClient->is_active,
                'access_tokens_count' => $oauthClient->access_tokens_count,
                'authorization_codes_count' => $oauthClient->authorization_codes_count,
                'created_at' => $oauthClient->created_at->format('M d, Y H:i:s'),
                'updated_at' => $oauthClient->updated_at->format('M d, Y H:i:s'),
                'has_secret' => !is_null($oauthClient->secret),
            ],
            'recentTokens' => $oauthClient->accessTokens()
                ->with('user')
                ->latest()
                ->limit(10)
                ->get()
                ->map(function ($token) {
                    return [
                        'id' => $token->id,
                        'user' => $token->user ? [
                            'id' => $token->user->id,
                            'name' => $token->user->name,
                            'email' => $token->user->email,
                        ] : null,
                        'scopes' => $token->scopes,
                        'expires_at' => $token->expires_at?->format('M d, Y H:i'),
                        'created_at' => $token->created_at->format('M d, Y H:i'),
                        'is_valid' => $token->isValid(),
                    ];
                }),
            'auditLogs' => AuditLog::where('entity_type', 'OAuthClient')
                ->where('entity_id', $oauthClient->id)
                ->with('user')
                ->latest()
                ->limit(10)
                ->get()
                ->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'event_type' => $log->event_type,
                        'user' => $log->user ? [
                            'name' => $log->user->name,
                            'email' => $log->user->email,
                        ] : null,
                        'ip_address' => $log->ip_address,
                        'created_at' => $log->created_at->format('M d, Y H:i:s'),
                    ];
                }),
            'clientSecret' => session('client_secret'),
        ]);
    }

    public function edit(OAuthClient $oauthClient): Response
    {
        $this->authorize('oauth_clients.edit');
        
        return Inertia::render('Admin/OAuthClients/Edit', [
            'client' => [
                'id' => $oauthClient->id,
                'name' => $oauthClient->name,
                'description' => $oauthClient->description,
                'redirect_uris' => $oauthClient->redirect_uris,
                'grants' => $oauthClient->grants,
                'scopes' => $oauthClient->scopes,
                'is_confidential' => $oauthClient->is_confidential,
                'is_active' => $oauthClient->is_active,
            ],
            'availableGrants' => [
                'authorization_code' => 'Authorization Code',
                'client_credentials' => 'Client Credentials',
                'refresh_token' => 'Refresh Token',
            ],
            'availableScopes' => [
                'read' => 'Read access',
                'write' => 'Write access',
                'openid' => 'OpenID Connect',
                'profile' => 'User profile',
                'email' => 'Email address',
            ],
        ]);
    }

    public function update(Request $request, OAuthClient $oauthClient)
    {
        $this->authorize('oauth_clients.edit');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'redirect_uris' => 'required|array|min:1',
            'redirect_uris.*' => 'required|url',
            'grants' => 'required|array|min:1',
            'grants.*' => 'required|string|in:authorization_code,client_credentials,refresh_token',
            'scopes' => 'nullable|array',
            'scopes.*' => 'required|string|in:read,write,openid,profile,email',
            'is_active' => 'boolean',
        ]);

        $oauthClient->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'redirect_uris' => $validated['redirect_uris'],
            'grants' => $validated['grants'],
            'scopes' => $validated['scopes'] ?? [],
            'is_active' => $validated['is_active'] ?? $oauthClient->is_active,
        ]);

        return redirect()->route('admin.oauth-clients.show', $oauthClient)
            ->with('success', 'Cliente OAuth atualizado com sucesso.');
    }

    public function destroy(OAuthClient $oauthClient)
    {
        $this->authorize('oauth_clients.delete');

        // Revoke all tokens for this client
        $oauthClient->accessTokens()->delete();
        $oauthClient->authorizationCodes()->delete();
        
        $oauthClient->delete();

        return redirect()->route('admin.oauth-clients.index')
            ->with('success', 'Cliente OAuth e todos os tokens associados foram excluídos.');
    }

    public function regenerateSecret(OAuthClient $oauthClient)
    {
        $this->authorize('oauth_clients.regenerate_secret');

        if (!$oauthClient->is_confidential) {
            return back()->with('error', 'Apenas clientes confidenciais possuem secret.');
        }

        // Generate new secret
        $newSecret = Str::random(64);
        
        $oauthClient->update([
            'secret' => hash('sha256', $newSecret),
        ]);

        // Optionally revoke existing tokens (security measure)
        if (request()->boolean('revoke_tokens')) {
            $oauthClient->accessTokens()->delete();
            $oauthClient->authorizationCodes()->delete();
        }

        // Store plain secret in session for one-time display
        session()->flash('client_secret', $newSecret);

        AuditLog::logEvent(
            'oauth_client_secret_regenerated',
            'OAuthClient',
            $oauthClient->id,
            null,
            ['revoke_tokens' => request()->boolean('revoke_tokens')]
        );

        return back()->with('success', 'Secret do cliente regenerado com sucesso.');
    }

    public function revokeTokens(OAuthClient $oauthClient)
    {
        $this->authorize('oauth_clients.edit');

        $tokenCount = $oauthClient->accessTokens()->count();
        $codeCount = $oauthClient->authorizationCodes()->count();

        $oauthClient->accessTokens()->delete();
        $oauthClient->authorizationCodes()->delete();

        AuditLog::logEvent(
            'oauth_client_tokens_revoked',
            'OAuthClient',
            $oauthClient->id,
            null,
            [
                'revoked_tokens' => $tokenCount,
                'revoked_codes' => $codeCount,
            ]
        );

        return back()->with('success', "Revogados {$tokenCount} tokens e {$codeCount} códigos de autorização.");
    }
}
