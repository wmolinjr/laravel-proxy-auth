<?php

namespace App\Http\Middleware;

use App\Models\Admin\SecurityEvent;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role = 'admin'): Response
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        // Check if user account is active
        if (!$user->is_active) {
            SecurityEvent::logEvent(
                'inactive_user_access_attempt',
                'high',
                [
                    'attempted_route' => $request->route()->getName(),
                    'attempted_url' => $request->url(),
                ],
                null,
                $user->id
            );

            Auth::logout();
            return redirect()->route('login')
                ->with('error', 'Sua conta foi desativada. Entre em contato com o administrador.');
        }

        // Check if user has admin role
        if (!$user->isAdmin()) {
            SecurityEvent::logEvent(
                'unauthorized_admin_access_attempt',
                'high',
                [
                    'attempted_route' => $request->route()->getName(),
                    'attempted_url' => $request->url(),
                    'user_roles' => $user->roles->pluck('name')->toArray(),
                ],
                null,
                $user->id
            );

            abort(403, 'Acesso negado. Esta área é restrita a administradores.');
        }

        // Additional role check if specific role is required
        if ($role !== 'admin' && !$user->hasRole($role)) {
            SecurityEvent::logEvent(
                'insufficient_role_access_attempt',
                'medium',
                [
                    'required_role' => $role,
                    'user_roles' => $user->roles->pluck('name')->toArray(),
                    'attempted_route' => $request->route()->getName(),
                ],
                null,
                $user->id
            );

            abort(403, "Acesso negado. É necessário ter a função '{$role}' para acessar esta área.");
        }

        // Check if user needs to change password
        if ($user->needsPasswordChange()) {
            // Allow access to password change routes
            if (!$request->routeIs(['password.*', 'logout'])) {
                return redirect()->route('password.request')
                    ->with('warning', 'Você deve alterar sua senha antes de continuar.');
            }
        }

        // Check if 2FA is required but not enabled
        if ($user->requires2FA() && !$user->two_factor_enabled) {
            // Allow access to 2FA setup routes
            if (!$request->routeIs(['two-factor.*', 'logout'])) {
                return redirect()->route('two-factor.setup')
                    ->with('warning', 'Autenticação de dois fatores é obrigatória para sua conta.');
            }
        }

        // Update last activity timestamp
        $user->touch('updated_at');

        return $next($request);
    }
}
