<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Middleware\Authorize;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public static function middleware(): array
    {
        return [
            'auth',
            new Authorize('can:audit_logs.view', only: ['index']),
            new Authorize('can:audit_logs.export', only: ['export']),
        ];
    }

    public function index(Request $request): Response
    {
        $query = AuditLog::with('user');

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('event_type', 'ilike', "%{$search}%")
                  ->orWhere('entity_type', 'ilike', "%{$search}%")
                  ->orWhereHas('user', function ($userQuery) use ($search) {
                      $userQuery->where('name', 'ilike', "%{$search}%")
                               ->orWhere('email', 'ilike', "%{$search}%");
                  });
            });
        }

        // Filter by event type
        if ($request->filled('event_type')) {
            $query->where('event_type', $request->get('event_type'));
        }

        // Filter by entity type
        if ($request->filled('entity_type')) {
            $query->where('entity_type', $request->get('entity_type'));
        }

        // Sorting
        $sortBy = $request->get('sort', 'created_at');
        $sortOrder = $request->get('order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $auditLogs = $query->paginate(20)->through(function ($log) {
            return [
                'id' => $log->id,
                'event_type' => $log->event_type,
                'entity_type' => $log->entity_type,
                'entity_id' => $log->entity_id,
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                    'email' => $log->user->email,
                    'avatar_url' => $log->user->avatar_url,
                ] : null,
                'ip_address' => $log->ip_address,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'metadata' => $log->metadata,
                'created_at' => $log->created_at->format('M d, Y H:i'),
            ];
        });

        return Inertia::render('admin/audit-logs/index', [
            'auditLogs' => $auditLogs,
            'filters' => $request->only(['search', 'event_type', 'entity_type', 'sort', 'order']),
            'eventTypes' => AuditLog::distinct()->pluck('event_type')->sort()->values(),
            'entityTypes' => AuditLog::whereNotNull('entity_type')->distinct()->pluck('entity_type')->sort()->values(),
            'stats' => [
                'total' => AuditLog::count(),
                'today' => AuditLog::whereDate('created_at', today())->count(),
                'this_week' => AuditLog::where('created_at', '>=', now()->subDays(7))->count(),
                'this_month' => AuditLog::where('created_at', '>=', now()->subDays(30))->count(),
            ]
        ]);
    }

    public function export(Request $request)
    {
        // TODO: Implement audit log export functionality
        return back()->with('info', 'Export functionality will be implemented soon');
    }
}