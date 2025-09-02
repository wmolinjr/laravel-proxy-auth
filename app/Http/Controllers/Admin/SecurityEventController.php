<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\SecurityEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Middleware\Authorize;
use Inertia\Inertia;
use Inertia\Response;

class SecurityEventController extends Controller
{
    public static function middleware(): array
    {
        return [
            'auth',
            new Authorize('can:security_events.view', only: ['index']),
            new Authorize('can:security_events.resolve', only: ['resolve']),
        ];
    }

    public function index(Request $request): Response
    {
        $query = SecurityEvent::with(['user', 'client']);

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('event_type', 'ilike', "%{$search}%")
                  ->orWhere('event_description', 'ilike', "%{$search}%")
                  ->orWhere('ip_address', 'ilike', "%{$search}%")
                  ->orWhereHas('user', function ($userQuery) use ($search) {
                      $userQuery->where('name', 'ilike', "%{$search}%")
                               ->orWhere('email', 'ilike', "%{$search}%");
                  });
            });
        }

        // Filter by severity
        if ($request->filled('severity')) {
            $query->where('severity', $request->get('severity'));
        }

        // Filter by status
        if ($request->filled('status')) {
            $status = $request->get('status');
            if ($status === 'unresolved') {
                $query->where('is_resolved', false);
            } elseif ($status === 'resolved') {
                $query->where('is_resolved', true);
            }
        }

        // Filter by event type
        if ($request->filled('event_type')) {
            $query->where('event_type', $request->get('event_type'));
        }

        // Sorting
        $sortBy = $request->get('sort', 'created_at');
        $sortOrder = $request->get('order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $securityEvents = $query->paginate(20)->through(function ($event) {
            return [
                'id' => $event->id,
                'event_type' => $event->event_type,
                'event_description' => $event->event_description,
                'severity' => $event->severity,
                'user' => $event->user ? [
                    'id' => $event->user->id,
                    'name' => $event->user->name,
                    'email' => $event->user->email,
                    'avatar_url' => $event->user->avatar_url,
                ] : null,
                'client' => $event->client ? [
                    'id' => $event->client->id,
                    'name' => $event->client->name,
                    'identifier' => $event->client->identifier,
                ] : null,
                'ip_address' => $event->ip_address,
                'country_code' => $event->country_code,
                'is_resolved' => $event->is_resolved,
                'resolved_at' => $event->resolved_at?->format('M d, Y H:i'),
                'resolution_notes' => $event->resolution_notes,
                'created_at' => $event->created_at->format('M d, Y H:i'),
            ];
        });

        return Inertia::render('admin/security-events/index', [
            'securityEvents' => $securityEvents,
            'filters' => $request->only(['search', 'severity', 'status', 'event_type', 'sort', 'order']),
            'eventTypes' => SecurityEvent::distinct()->pluck('event_type')->sort()->values(),
            'stats' => [
                'total' => SecurityEvent::count(),
                'unresolved' => SecurityEvent::where('is_resolved', false)->count(),
                'high_severity' => SecurityEvent::where('severity', 'high')
                    ->orWhere('severity', 'critical')
                    ->count(),
                'resolved_today' => SecurityEvent::where('is_resolved', true)
                    ->whereDate('resolved_at', today())
                    ->count(),
            ]
        ]);
    }

    public function resolve(Request $request, SecurityEvent $securityEvent)
    {
        $validated = $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        $securityEvent->resolve($validated['notes'] ?? '');

        return back()->with('success', 'Evento de segurança resolvido com sucesso.');
    }
}