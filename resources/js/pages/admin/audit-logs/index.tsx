import { DataTable, Column } from '@/components/ui/data-table';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import AppLayout from '@/layouts/app-layout';
import { adminRoutes } from '@/lib/admin-routes';
import { type BreadcrumbItem, type AuditLog, type PaginatedResponse } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Download } from 'lucide-react';

interface AuditLogsIndexProps {
  auditLogs: PaginatedResponse<AuditLog>;
  filters: {
    search?: string;
    event_type?: string;
    entity_type?: string;
    sort?: string;
    order?: string;
  };
  eventTypes: string[];
  entityTypes: string[];
  stats: {
    total: number;
    today: number;
    this_week: number;
    this_month: number;
  };
}

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Admin', href: adminRoutes.dashboard() },
  { title: 'Audit Logs', href: adminRoutes.auditLogs.index() },
];

export default function AuditLogsIndex({ 
  auditLogs, 
  filters, 
  eventTypes = [], 
  entityTypes = [], 
  stats 
}: AuditLogsIndexProps) {
  const handleFilter = (key: keyof typeof filters, value: string) => {
    const newFilters = { ...filters, [key]: value };
    if (value === '' || value === 'all') {
      delete newFilters[key];
    }
    
    router.get(adminRoutes.auditLogs.index(), newFilters, {
      preserveState: true,
      preserveScroll: true,
    });
  };

  const columns: Column<AuditLog>[] = [
    {
      key: 'event_type',
      label: 'Event Type',
      render: (value) => (
        <Badge variant="outline" className="font-mono text-xs">
          {String(value).replace('_', ' ').replace(/\b\w/g, (l: string) => l.toUpperCase())}
        </Badge>
      ),
    },
    {
      key: 'entity',
      label: 'Entity',
      render: (_, log) => (
        log.entity_type ? (
          <div className="flex flex-col">
            <span className="font-medium text-sm">{log.entity_type}</span>
            {log.entity_id && (
              <span className="text-xs text-muted-foreground">#{log.entity_id}</span>
            )}
          </div>
        ) : (
          <span className="text-sm text-muted-foreground">System</span>
        )
      ),
    },
    {
      key: 'user',
      label: 'User',
      render: (_, log) => (
        log.user ? (
          <div className="flex items-center gap-2">
            <Avatar className="h-6 w-6">
              <AvatarImage src={log.user.avatar_url} alt={log.user.name} />
              <AvatarFallback className="text-xs">
                {log.user.name.split(' ').map(n => n[0]).join('').toUpperCase()}
              </AvatarFallback>
            </Avatar>
            <div className="flex flex-col">
              <span className="text-sm font-medium">{log.user.name}</span>
              <span className="text-xs text-muted-foreground">{log.user.email}</span>
            </div>
          </div>
        ) : (
          <span className="text-sm text-muted-foreground">System</span>
        )
      ),
    },
    {
      key: 'ip_address',
      label: 'IP Address',
      render: (value) => (
        <span className="font-mono text-sm text-muted-foreground">
          {value || '-'}
        </span>
      ),
    },
    {
      key: 'changes',
      label: 'Changes',
      render: (_, log) => {
        const hasChanges = log.old_values || log.new_values;
        return hasChanges ? (
          <Badge variant="secondary" className="text-xs">
            {log.old_values && log.new_values ? 'Updated' : 
             log.new_values ? 'Created' : 
             log.old_values ? 'Deleted' : 'Modified'}
          </Badge>
        ) : (
          <span className="text-sm text-muted-foreground">-</span>
        );
      },
    },
    {
      key: 'metadata',
      label: 'Details',
      render: (_, log) => (
        log.metadata ? (
          <Badge variant="outline" className="text-xs">
            Has Details
          </Badge>
        ) : (
          <span className="text-sm text-muted-foreground">-</span>
        )
      ),
    },
    {
      key: 'created_at',
      label: 'Date & Time',
      render: (value) => (
        <span className="text-sm text-muted-foreground">{value}</span>
      ),
    },
  ];

  const filtersComponent = (
    <div className="flex items-center gap-4">
      <Select 
        value={filters.event_type || 'all'} 
        onValueChange={(value) => handleFilter('event_type', value)}
      >
        <SelectTrigger className="w-48">
          <SelectValue placeholder="Filter by event type" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">All event types</SelectItem>
          {eventTypes.map((type) => (
            <SelectItem key={type} value={type}>
              {type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>

      <Select 
        value={filters.entity_type || 'all'} 
        onValueChange={(value) => handleFilter('entity_type', value)}
      >
        <SelectTrigger className="w-40">
          <SelectValue placeholder="Entity type" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">All entities</SelectItem>
          {entityTypes.map((type) => (
            <SelectItem key={type} value={type}>
              {type}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
    </div>
  );

  const actionsComponent = (
    <div className="flex items-center gap-2">
      <Button variant="outline" asChild>
        <Link href={adminRoutes.auditLogs.export()}>
          <Download className="h-4 w-4 mr-2" />
          Export Logs
        </Link>
      </Button>
    </div>
  );

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Audit Logs" />
      
      <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold tracking-tight">Audit Logs</h1>
            <p className="text-muted-foreground">
              Track all system activities and changes
            </p>
          </div>
        </div>

        {/* Stats */}
        <div className="grid gap-4 md:grid-cols-4">
          <div className="rounded-lg border p-4">
            <div className="text-2xl font-bold">{stats.total?.toLocaleString() || 0}</div>
            <div className="text-sm text-muted-foreground">Total Events</div>
          </div>
          <div className="rounded-lg border p-4">
            <div className="text-2xl font-bold text-blue-600">{stats.today?.toLocaleString() || 0}</div>
            <div className="text-sm text-muted-foreground">Today</div>
          </div>
          <div className="rounded-lg border p-4">
            <div className="text-2xl font-bold text-green-600">{stats.this_week?.toLocaleString() || 0}</div>
            <div className="text-sm text-muted-foreground">This Week</div>
          </div>
          <div className="rounded-lg border p-4">
            <div className="text-2xl font-bold text-purple-600">{stats.this_month?.toLocaleString() || 0}</div>
            <div className="text-sm text-muted-foreground">This Month</div>
          </div>
        </div>

        {/* Audit Logs Table */}
        <DataTable
          data={auditLogs.data}
          columns={columns}
          pagination={auditLogs}
          searchable
          searchPlaceholder="Search audit logs..."
          onSearch={(query) => handleFilter('search', query)}
          filters={filtersComponent}
          actions={actionsComponent}
          emptyMessage="No audit logs found"
        />
      </div>
    </AppLayout>
  );
}