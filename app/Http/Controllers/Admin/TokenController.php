<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\AuditLog;
use App\Models\OAuth\OAuthAccessToken;
use App\Models\OAuth\OAuthClient;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Middleware\Authorize;
use Inertia\Inertia;
use Inertia\Response;

class TokenController extends Controller
{
    public static function middleware(): array
    {
        return [
            'auth',
            new Authorize('can:tokens.view', only: ['index', 'show']),
            new Authorize('can:tokens.revoke', only: ['destroy', 'revoke', 'revokeAll']),
            new Authorize('can:tokens.create', only: ['create', 'store']),
        ];
    }

    public function index(Request $request): Response
    {
        $query = OAuthAccessToken::with(['user', 'client']);

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('name', 'ilike', "%{$search}%")
                             ->orWhere('email', 'ilike', "%{$search}%");
                })->orWhereHas('client', function ($clientQuery) use ($search) {
                    $clientQuery->where('name', 'ilike', "%{$search}%")
                               ->orWhere('identifier', 'ilike', "%{$search}%");
                })->orWhere('identifier', 'ilike', "%{$search}%");
            });
        }

        // Filter by client
        if ($request->filled('client')) {
            $query->where('client_id', $request->get('client'));
        }

        // Filter by user
        if ($request->filled('user')) {
            $query->where('user_id', $request->get('user'));
        }

        // Filter by status
        if ($request->filled('status')) {
            $status = $request->get('status');
            if ($status === 'valid') {
                $query->where('expires_at', '>', now())->whereNull('revoked_at');
            } elseif ($status === 'expired') {
                $query->where('expires_at', '<=', now());
            } elseif ($status === 'revoked') {
                $query->whereNotNull('revoked_at');
            }
        }

        // Filter by scope
        if ($request->filled('scope')) {
            $scope = $request->get('scope');
            $query->whereJsonContains('scopes', $scope);
        }

        // Sorting
        $sortBy = $request->get('sort', 'created_at');
        $sortOrder = $request->get('order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $tokens = $query->paginate(20)->through(function ($token) {
            return [
                'id' => $token->id,
                'identifier' => $token->identifier,
                'user' => $token->user ? [
                    'id' => $token->user->id,
                    'name' => $token->user->name,
                    'email' => $token->user->email,
                    'avatar_url' => $token->user->avatar_url,
                ] : null,
                'client' => $token->client ? [
                    'id' => $token->client->id,
                    'name' => $token->client->name,
                    'identifier' => $token->client->identifier,
                ] : null,
                'scopes' => $token->getScopes(),
                'expires_at' => $token->expires_at?->format('M d, Y H:i'),
                'created_at' => $token->created_at->format('M d, Y H:i'),
                'revoked_at' => $token->revoked_at?->format('M d, Y H:i'),
                'is_valid' => $token->isValid(),
                'is_expired' => $token->isExpired(),
                'is_revoked' => !is_null($token->revoked_at),
            ];
        });

        return Inertia::render('admin/tokens/index', [
            'tokens' => $tokens,
            'filters' => $request->only(['search', 'client', 'user', 'status', 'scope', 'sort', 'order']),
            'clients' => OAuthClient::select('id', 'name', 'identifier')->get(),
            'users' => User::select('id', 'name', 'email')
                ->whereHas('accessTokens')
                ->limit(100)
                ->get(),
            'availableScopes' => ['read', 'write', 'openid', 'profile', 'email'],
            'stats' => [
                'total' => OAuthAccessToken::count(),
                'valid' => OAuthAccessToken::valid()->count(),
                'expired' => OAuthAccessToken::expired()->count(),
                'revoked' => OAuthAccessToken::revoked()->count(),
                'issued_today' => OAuthAccessToken::whereDate('created_at', today())->count(),
            ]
        ]);
    }

    public function show(OAuthAccessToken $token): Response
    {
        // Authorization handled by middleware
        // $this->authorize('tokens.view');

        $token->load(['user', 'client']);

        return Inertia::render('admin/tokens/show', [
            'token' => [
                'id' => $token->id,
                'identifier' => $token->identifier,
                'user' => $token->user ? [
                    'id' => $token->user->id,
                    'name' => $token->user->name,
                    'email' => $token->user->email,
                    'avatar_url' => $token->user->avatar_url,
                    'department' => $token->user->department,
                    'job_title' => $token->user->job_title,
                ] : null,
                'client' => $token->client ? [
                    'id' => $token->client->id,
                    'name' => $token->client->name,
                    'identifier' => $token->client->identifier,
                    'description' => $token->client->description,
                ] : null,
                'scopes' => $token->getScopes(),
                'expires_at' => $token->expires_at?->format('M d, Y H:i:s'),
                'created_at' => $token->created_at->format('M d, Y H:i:s'),
                'updated_at' => $token->updated_at->format('M d, Y H:i:s'),
                'revoked_at' => $token->revoked_at?->format('M d, Y H:i:s'),
                'is_valid' => $token->isValid(),
                'is_expired' => $token->isExpired(),
                'is_revoked' => !is_null($token->revoked_at),
                'time_until_expiry' => $token->expires_at ? $token->expires_at->diffForHumans() : null,
            ],
            'auditLogs' => AuditLog::where('entity_type', 'OAuthAccessToken')
                ->where('entity_id', $token->id)
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
        ]);
    }

    public function destroy(OAuthAccessToken $token)
    {
        // Authorization handled by middleware
        // $this->authorize('tokens.revoke');

        if ($token->revoked_at) {
            return back()->with('error', 'Este token já foi revogado.');
        }

        $token->revoke();

        AuditLog::logEvent(
            'oauth_token_revoked',
            'OAuthAccessToken',
            $token->id,
            null,
            [
                'user_id' => $token->user_id,
                'client_id' => $token->client_id,
                'scopes' => $token->getScopes(),
            ]
        );

        return redirect()->route('tokens.index')
            ->with('success', 'Token revogado com sucesso.');
    }

    public function revoke(Request $request)
    {
        // Authorization handled by middleware
        // $this->authorize('tokens.revoke');

        $validated = $request->validate([
            'token_ids' => 'required|array|min:1',
            'token_ids.*' => 'required|integer|exists:oauth_access_tokens,id',
        ]);

        $tokens = OAuthAccessToken::whereIn('id', $validated['token_ids'])
            ->whereNull('revoked_at')
            ->get();

        $revokedCount = 0;
        foreach ($tokens as $token) {
            $token->revoke();
            $revokedCount++;

            AuditLog::logEvent(
                'oauth_token_revoked',
                'OAuthAccessToken',
                $token->id,
                null,
                [
                    'bulk_operation' => true,
                    'user_id' => $token->user_id,
                    'client_id' => $token->client_id,
                ]
            );
        }

        return back()->with('success', "Revogados {$revokedCount} tokens com sucesso.");
    }

    public function revokeAll(Request $request)
    {
        // Authorization handled by middleware
        // $this->authorize('tokens.revoke');

        $validated = $request->validate([
            'user_id' => 'nullable|integer|exists:users,id',
            'client_id' => 'nullable|integer|exists:oauth_clients,id',
            'expired_only' => 'boolean',
        ]);

        $query = OAuthAccessToken::whereNull('revoked_at');

        if (!empty($validated['user_id'])) {
            $query->where('user_id', $validated['user_id']);
        }

        if (!empty($validated['client_id'])) {
            $query->where('client_id', $validated['client_id']);
        }

        if ($validated['expired_only'] ?? false) {
            $query->where('expires_at', '<=', now());
        }

        $count = $query->count();
        
        if ($count === 0) {
            return back()->with('info', 'Nenhum token encontrado para revogar.');
        }

        // Revoke tokens in batches to avoid memory issues
        $query->chunk(100, function ($tokens) {
            foreach ($tokens as $token) {
                $token->revoke();
            }
        });

        AuditLog::logEvent(
            'oauth_tokens_bulk_revoked',
            null,
            null,
            null,
            [
                'count' => $count,
                'user_id' => $validated['user_id'] ?? null,
                'client_id' => $validated['client_id'] ?? null,
                'expired_only' => $validated['expired_only'] ?? false,
            ]
        );

        return back()->with('success', "Revogados {$count} tokens com sucesso.");
    }

    public function cleanup(Request $request)
    {
        // Authorization handled by middleware
        // $this->authorize('tokens.revoke');

        $validated = $request->validate([
            'days_old' => 'required|integer|min:1|max:365',
            'revoked_only' => 'boolean',
        ]);

        $query = OAuthAccessToken::where('created_at', '<=', now()->subDays($validated['days_old']));

        if ($validated['revoked_only'] ?? false) {
            $query->whereNotNull('revoked_at');
        }

        $count = $query->count();

        if ($count === 0) {
            return back()->with('info', 'Nenhum token encontrado para limpeza.');
        }

        $query->delete();

        AuditLog::logEvent(
            'oauth_tokens_cleanup',
            null,
            null,
            null,
            [
                'count' => $count,
                'days_old' => $validated['days_old'],
                'revoked_only' => $validated['revoked_only'] ?? false,
            ]
        );

        return back()->with('success', "Removidos {$count} tokens antigos do sistema.");
    }
}
