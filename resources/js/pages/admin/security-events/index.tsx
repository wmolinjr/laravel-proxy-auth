import { DataTable, Column, StatusBadge } from '@/components/ui/data-table';
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
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog";
import { Textarea } from "@/components/ui/textarea";
import { Label } from "@/components/ui/label";
import AppLayout from '@/layouts/app-layout';
import { adminRoutes } from '@/lib/admin-routes';
import { type BreadcrumbItem, type SecurityEvent, type PaginatedResponse } from '@/types';
import { Head, router } from '@inertiajs/react';
import { CheckCircle, AlertTriangle, XCircle, Clock } from 'lucide-react';
import { useState } from 'react';

interface SecurityEventsIndexProps {
  securityEvents: PaginatedResponse<SecurityEvent>;
  filters: {
    search?: string;
    severity?: string;
    status?: string;
    event_type?: string;
    sort?: string;
    order?: string;
  };
  eventTypes: string[];
  stats: {
    total: number;
    unresolved: number;
    high_severity: number;
    resolved_today: number;
  };
}

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Admin', href: adminRoutes.dashboard() },
  { title: 'Security Events', href: adminRoutes.securityEvents.index() },
];

export default function SecurityEventsIndex({ 
  securityEvents, 
  filters, 
  eventTypes = [], 
  stats 
}: SecurityEventsIndexProps) {
  const [resolveDialog, setResolveDialog] = useState<SecurityEvent | null>(null);
  const [resolutionNotes, setResolutionNotes] = useState('');

  const handleFilter = (key: string, value: string) => {
    const newFilters = { ...filters, [key]: value };
    if (value === '' || value === 'all') {
      delete newFilters[key];
    }
    
    router.get(adminRoutes.securityEvents.index(), newFilters, {
      preserveState: true,
      preserveScroll: true,
    });
  };

  const handleResolve = (event: SecurityEvent) => {
    setResolveDialog(event);
    setResolutionNotes('');
  };

  const submitResolve = () => {
    if (!resolveDialog) return;
    
    router.post(adminRoutes.securityEvents.resolve(resolveDialog.id), {
      notes: resolutionNotes
    }, {
      onSuccess: () => {
        setResolveDialog(null);
        setResolutionNotes('');
      }
    });
  };

  const getSeverityIcon = (severity: string) => {
    switch (severity) {
      case 'critical':
        return <XCircle className="h-4 w-4 text-red-600" />;
      case 'high':
        return <AlertTriangle className="h-4 w-4 text-orange-600" />;
      case 'medium':
        return <Clock className="h-4 w-4 text-yellow-600" />;
      case 'low':
        return <CheckCircle className="h-4 w-4 text-green-600" />;
      default:
        return <AlertTriangle className="h-4 w-4 text-gray-600" />;
    }
  };

  const columns: Column<SecurityEvent>[] = [
    {
      key: 'severity',
      label: 'Severity',
      render: (_, event) => (
        <div className="flex items-center gap-2">
          {getSeverityIcon(event.severity)}
          <StatusBadge 
            status={event.severity.toUpperCase()}
            variant={
              event.severity === 'critical' || event.severity === 'high' 
                ? 'destructive' 
                : event.severity === 'medium' 
                ? 'default' 
                : 'secondary'
            }
          />
        </div>
      ),
    },
    {
      key: 'event_description',
      label: 'Event',
      render: (_, event) => (
        <div className="flex flex-col max-w-xs">
          <span className="font-medium text-sm">
            {event.event_description || event.event_type}
          </span>
          <span className="text-xs text-muted-foreground font-mono">
            {event.event_type}
          </span>
        </div>
      ),
    },
    {
      key: 'user',
      label: 'User',
      render: (_, event) => (
        event.user ? (
          <div className="flex items-center gap-2">
            <Avatar className="h-6 w-6">
              <AvatarImage src={event.user.avatar_url} alt={event.user.name} />
              <AvatarFallback className="text-xs">
                {event.user.name.split(' ').map(n => n[0]).join('').toUpperCase()}
              </AvatarFallback>
            </Avatar>
            <div className="flex flex-col">
              <span className="text-sm font-medium">{event.user.name}</span>
              <span className="text-xs text-muted-foreground">{event.user.email}</span>
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
      render: (value, event) => (
        <div className="flex flex-col">
          <span className="font-mono text-sm">{value || '-'}</span>
          {event.country_code && (
            <span className="text-xs text-muted-foreground">
              {event.country_code}
            </span>
          )}
        </div>
      ),
    },
    {
      key: 'client',
      label: 'Client',
      render: (_, event) => (
        event.client ? (
          <div className="flex flex-col">
            <span className="text-sm font-medium">{event.client.name}</span>
            <span className="text-xs text-muted-foreground font-mono">
              {event.client.identifier}
            </span>
          </div>
        ) : (
          <span className="text-sm text-muted-foreground">-</span>
        )
      ),
    },
    {
      key: 'status',
      label: 'Status',
      render: (_, event) => (
        <div className="flex flex-col gap-1">
          <StatusBadge 
            status={event.is_resolved ? 'Resolved' : 'Open'}
            variant={event.is_resolved ? 'default' : 'destructive'}
          />
          {event.resolved_at && (
            <span className="text-xs text-muted-foreground">
              {event.resolved_at}
            </span>
          )}
        </div>
      ),
    },
    {
      key: 'created_at',
      label: 'Created',
      render: (value) => (
        <span className="text-sm text-muted-foreground">{value}</span>
      ),
    },
    {
      key: 'actions',
      label: 'Actions',
      render: (_, event) => (
        !event.is_resolved ? (
          <Button 
            variant="outline" 
            size="sm"
            onClick={() => handleResolve(event)}
          >
            <CheckCircle className="h-4 w-4 mr-1" />
            Resolve
          </Button>
        ) : (
          <Badge variant="outline" className="text-xs">
            Resolved
          </Badge>
        )
      ),
      className: "w-24",
    },
  ];

  const filtersComponent = (
    <div className="flex items-center gap-4">
      <Select 
        value={filters.severity || 'all'} 
        onValueChange={(value) => handleFilter('severity', value)}
      >
        <SelectTrigger className="w-32">
          <SelectValue placeholder="Severity" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">All levels</SelectItem>
          <SelectItem value="critical">Critical</SelectItem>
          <SelectItem value="high">High</SelectItem>
          <SelectItem value="medium">Medium</SelectItem>
          <SelectItem value="low">Low</SelectItem>
        </SelectContent>
      </Select>

      <Select 
        value={filters.status || 'all'} 
        onValueChange={(value) => handleFilter('status', value)}
      >
        <SelectTrigger className="w-32">
          <SelectValue placeholder="Status" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">All status</SelectItem>
          <SelectItem value="unresolved">Unresolved</SelectItem>
          <SelectItem value="resolved">Resolved</SelectItem>
        </SelectContent>
      </Select>

      <Select 
        value={filters.event_type || 'all'} 
        onValueChange={(value) => handleFilter('event_type', value)}
      >
        <SelectTrigger className="w-48">
          <SelectValue placeholder="Event type" />
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
    </div>
  );

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Security Events" />
      
      <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold tracking-tight">Security Events</h1>
            <p className="text-muted-foreground">
              Monitor and respond to security incidents
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
            <div className="text-2xl font-bold text-red-600">{stats.unresolved?.toLocaleString() || 0}</div>
            <div className="text-sm text-muted-foreground">Unresolved</div>
          </div>
          <div className="rounded-lg border p-4">
            <div className="text-2xl font-bold text-orange-600">{stats.high_severity?.toLocaleString() || 0}</div>
            <div className="text-sm text-muted-foreground">High Severity</div>
          </div>
          <div className="rounded-lg border p-4">
            <div className="text-2xl font-bold text-green-600">{stats.resolved_today?.toLocaleString() || 0}</div>
            <div className="text-sm text-muted-foreground">Resolved Today</div>
          </div>
        </div>

        {/* Security Events Table */}
        <DataTable
          data={securityEvents.data}
          columns={columns}
          pagination={securityEvents}
          searchable
          searchPlaceholder="Search security events..."
          onSearch={(query) => handleFilter('search', query)}
          filters={filtersComponent}
          emptyMessage="No security events found"
        />

        {/* Resolve Dialog */}
        <Dialog open={!!resolveDialog} onOpenChange={(open) => !open && setResolveDialog(null)}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>Resolve Security Event</DialogTitle>
              <DialogDescription>
                Mark this security event as resolved and add resolution notes.
              </DialogDescription>
            </DialogHeader>
            
            {resolveDialog && (
              <div className="space-y-4">
                <div className="p-4 border rounded-lg bg-muted/50">
                  <div className="flex items-center gap-2 mb-2">
                    {getSeverityIcon(resolveDialog.severity)}
                    <Badge variant={
                      resolveDialog.severity === 'critical' || resolveDialog.severity === 'high' 
                        ? 'destructive' 
                        : resolveDialog.severity === 'medium' 
                        ? 'default' 
                        : 'secondary'
                    }>
                      {resolveDialog.severity.toUpperCase()}
                    </Badge>
                  </div>
                  <div className="font-medium">
                    {resolveDialog.event_description || resolveDialog.event_type}
                  </div>
                  <div className="text-sm text-muted-foreground">
                    {resolveDialog.created_at} • {resolveDialog.ip_address}
                  </div>
                </div>

                <div className="space-y-2">
                  <Label htmlFor="resolution-notes">Resolution Notes</Label>
                  <Textarea
                    id="resolution-notes"
                    placeholder="Describe how this event was resolved..."
                    value={resolutionNotes}
                    onChange={(e) => setResolutionNotes(e.target.value)}
                    className="min-h-[100px]"
                  />
                </div>
              </div>
            )}

            <DialogFooter>
              <Button variant="outline" onClick={() => setResolveDialog(null)}>
                Cancel
              </Button>
              <Button onClick={submitResolve}>
                <CheckCircle className="h-4 w-4 mr-2" />
                Resolve Event
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </div>
    </AppLayout>
  );
}