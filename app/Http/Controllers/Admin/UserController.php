<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:users.view')->only(['index', 'show']);
        $this->middleware('can:users.create')->only(['create', 'store']);
        $this->middleware('can:users.edit')->only(['edit', 'update']);
        $this->middleware('can:users.delete')->only(['destroy']);
        $this->middleware('can:users.manage_roles')->only(['assignRole', 'removeRole']);
    }

    public function index(Request $request): Response
    {
        $query = User::with(['roles'])
            ->withTrashed();

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%")
                  ->orWhere('department', 'ilike', "%{$search}%")
                  ->orWhere('job_title', 'ilike', "%{$search}%");
            });
        }

        // Filter by role
        if ($request->filled('role')) {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('name', $request->get('role'));
            });
        }

        // Filter by status
        if ($request->filled('status')) {
            $status = $request->get('status');
            if ($status === 'active') {
                $query->where('is_active', true)->whereNull('deleted_at');
            } elseif ($status === 'inactive') {
                $query->where('is_active', false)->whereNull('deleted_at');
            } elseif ($status === 'deleted') {
                $query->whereNotNull('deleted_at');
            }
        }

        // Sorting
        $sortBy = $request->get('sort', 'created_at');
        $sortOrder = $request->get('order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $users = $query->paginate(15)->through(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'department' => $user->department,
                'job_title' => $user->job_title,
                'is_active' => $user->is_active,
                'avatar_url' => $user->avatar_url,
                'last_login_at' => $user->last_login_at?->format('M d, Y H:i'),
                'created_at' => $user->created_at->format('M d, Y'),
                'deleted_at' => $user->deleted_at?->format('M d, Y'),
                'roles' => $user->roles->pluck('name'),
                'is_admin' => $user->isAdmin(),
                'is_super_admin' => $user->isSuperAdmin(),
            ];
        });

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => $request->only(['search', 'role', 'status', 'sort', 'order']),
            'roles' => Role::all()->pluck('name'),
            'stats' => [
                'total' => User::count(),
                'active' => User::active()->count(),
                'inactive' => User::where('is_active', false)->count(),
                'deleted' => User::onlyTrashed()->count(),
                'admins' => User::admins()->count(),
            ]
        ]);
    }

    public function create(): Response
    {
        $this->authorize('users.create');
        
        return Inertia::render('Admin/Users/Create', [
            'roles' => Role::all()->map(function ($role) {
                return [
                    'value' => $role->name,
                    'label' => ucwords(str_replace('-', ' ', $role->name)),
                ];
            }),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('users.create');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'department' => 'nullable|string|max:100',
            'job_title' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:20',
            'timezone' => 'nullable|string|max:50',
            'locale' => 'nullable|string|max:10',
            'is_active' => 'boolean',
            'roles' => 'array',
            'roles.*' => 'string|exists:roles,name',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'department' => $validated['department'] ?? null,
            'job_title' => $validated['job_title'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'timezone' => $validated['timezone'] ?? config('app.timezone'),
            'locale' => $validated['locale'] ?? config('app.locale'),
            'is_active' => $validated['is_active'] ?? true,
            'email_verified_at' => now(),
            'password_changed_at' => now(),
        ]);

        // Assign roles if provided
        if (!empty($validated['roles'])) {
            $user->assignRole($validated['roles']);
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'Usuário criado com sucesso.');
    }

    public function show(User $user): Response
    {
        $this->authorize('users.view');

        $user->load(['roles', 'accessTokens' => function ($query) {
            $query->with('client')->latest()->limit(10);
        }]);

        return Inertia::render('Admin/Users/Show', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'department' => $user->department,
                'job_title' => $user->job_title,
                'phone' => $user->phone,
                'timezone' => $user->timezone,
                'locale' => $user->locale,
                'is_active' => $user->is_active,
                'avatar_url' => $user->avatar_url,
                'full_name' => $user->full_name,
                'last_login_at' => $user->last_login_at?->format('M d, Y H:i:s'),
                'password_changed_at' => $user->password_changed_at?->format('M d, Y'),
                'two_factor_enabled' => $user->two_factor_enabled,
                'created_at' => $user->created_at->format('M d, Y H:i:s'),
                'updated_at' => $user->updated_at->format('M d, Y H:i:s'),
                'deleted_at' => $user->deleted_at?->format('M d, Y H:i:s'),
                'roles' => $user->roles->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                        'display_name' => ucwords(str_replace('-', ' ', $role->name)),
                    ];
                }),
                'is_admin' => $user->isAdmin(),
                'is_super_admin' => $user->isSuperAdmin(),
                'needs_password_change' => $user->needsPasswordChange(),
                'requires_2fa' => $user->requires2FA(),
                'active_tokens_count' => $user->active_tokens_count,
            ],
            'recentTokens' => $user->accessTokens->map(function ($token) {
                return [
                    'id' => $token->id,
                    'client' => $token->client ? [
                        'name' => $token->client->name,
                        'identifier' => $token->client->identifier,
                    ] : null,
                    'scopes' => $token->scopes,
                    'expires_at' => $token->expires_at?->format('M d, Y H:i'),
                    'created_at' => $token->created_at->format('M d, Y H:i'),
                    'is_valid' => $token->isValid(),
                ];
            }),
            'auditLogs' => AuditLog::where('entity_type', 'User')
                ->where('entity_id', $user->id)
                ->with('user')
                ->latest()
                ->limit(20)
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
                        'old_values' => $log->old_values,
                        'new_values' => $log->new_values,
                    ];
                }),
        ]);
    }

    public function edit(User $user): Response
    {
        $this->authorize('users.edit');
        
        return Inertia::render('Admin/Users/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'department' => $user->department,
                'job_title' => $user->job_title,
                'phone' => $user->phone,
                'timezone' => $user->timezone,
                'locale' => $user->locale,
                'is_active' => $user->is_active,
                'roles' => $user->roles->pluck('name'),
            ],
            'roles' => Role::all()->map(function ($role) {
                return [
                    'value' => $role->name,
                    'label' => ucwords(str_replace('-', ' ', $role->name)),
                ];
            }),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $this->authorize('users.edit');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:8|confirmed',
            'department' => 'nullable|string|max:100',
            'job_title' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:20',
            'timezone' => 'nullable|string|max:50',
            'locale' => 'nullable|string|max:10',
            'is_active' => 'boolean',
            'roles' => 'array',
            'roles.*' => 'string|exists:roles,name',
        ]);

        $userData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'department' => $validated['department'] ?? null,
            'job_title' => $validated['job_title'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'timezone' => $validated['timezone'] ?? $user->timezone,
            'locale' => $validated['locale'] ?? $user->locale,
            'is_active' => $validated['is_active'] ?? $user->is_active,
        ];

        // Update password if provided
        if (!empty($validated['password'])) {
            $userData['password'] = Hash::make($validated['password']);
            $userData['password_changed_at'] = now();
        }

        $user->update($userData);

        // Update roles if user has permission and roles are provided
        if (auth()->user()->can('users.manage_roles') && array_key_exists('roles', $validated)) {
            $user->syncRoles($validated['roles']);
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'Usuário atualizado com sucesso.');
    }

    public function destroy(User $user)
    {
        $this->authorize('users.delete');

        // Prevent deleting super admins
        if ($user->isSuperAdmin()) {
            return back()->with('error', 'Super administradores não podem ser excluídos.');
        }

        // Prevent self-deletion
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Você não pode excluir sua própria conta.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'Usuário excluído com sucesso.');
    }

    public function restore(User $user)
    {
        $this->authorize('users.edit');
        
        $user->restore();
        
        return back()->with('success', 'Usuário restaurado com sucesso.');
    }

    public function forceDelete(User $user)
    {
        $this->authorize('users.delete');
        
        // Prevent deleting super admins
        if ($user->isSuperAdmin()) {
            return back()->with('error', 'Super administradores não podem ser excluídos permanentemente.');
        }

        $user->forceDelete();
        
        return redirect()->route('admin.users.index')
            ->with('success', 'Usuário excluído permanentemente.');
    }
}
