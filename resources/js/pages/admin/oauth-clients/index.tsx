import { DataTable, Column, StatusBadge, ActionButton } from '@/components/ui/data-table';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { HealthIndicator } from '@/components/ui/health-status';
import { MetricCard } from '@/components/ui/monitoring-metrics';
import { Card, CardContent } from '@/components/ui/card';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from "@/components/ui/alert-dialog";
import AppLayout from '@/layouts/app-layout';
import { adminRoutes } from '@/lib/admin-routes';
import { type BreadcrumbItem, type OAuthClient, type PaginatedResponse } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, Eye, Edit, Trash2, Key, Settings, Activity, AlertTriangle, Clock, RefreshCw } from 'lucide-react';

interface OAuthClientsIndexProps {
  clients: PaginatedResponse<OAuthClient>;
  filters: {
    search?: string;
    status?: string;
    confidential?: string;
    health_status?: string;
    environment?: string;
    maintenance_mode?: string;
    sort?: string;
    order?: string;
  };
  stats: {
    total: number;
    active: number;
    confidential: number;
    public: number;
    healthy: number;
    unhealthy: number;
    maintenance: number;
    production: number;
    staging: number;
    development: number;
  };
}

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Admin', href: adminRoutes.dashboard() },
  { title: 'OAuth Clients', href: adminRoutes.oauthClients.index() },
];

export default function OAuthClientsIndex({ clients, filters, stats }: OAuthClientsIndexProps) {
  const handleFilter = (key: keyof typeof filters, value: string) => {
    const newFilters = { ...filters, [key]: value };
    if (value === '' || value === 'all') {
      delete newFilters[key];
    }
    
    router.get(adminRoutes.oauthClients.index(), newFilters, {
      preserveState: true,
      preserveScroll: true,
    });
  };

  const columns: Column<OAuthClient>[] = [
    {
      key: 'client',
      label: 'Client',
      render: (_, client) => (
        <div className="flex flex-col">
          <span className="font-medium">{client.name}</span>
          <span className="text-sm text-muted-foreground font-mono">
            {client.identifier}
          </span>
        </div>
      ),
    },
    {
      key: 'description',
      label: 'Description',
      render: (value) => (
        <span className="text-sm text-muted-foreground max-w-xs truncate">
          {value || '-'}
        </span>
      ),
    },
    {
      key: 'type',
      label: 'Type',
      render: (_, client) => (
        <div className="flex flex-col gap-1">
          <StatusBadge 
            status={client.is_confidential ? 'Confidential' : 'Public'}
            variant={client.is_confidential ? 'default' : 'secondary'}
          />
          <Badge variant="outline" className="text-xs capitalize">
            {client.environment}
          </Badge>
          {client.has_secret && (
            <div className="flex items-center gap-1 text-xs text-muted-foreground">
              <Key className="h-3 w-3" />
              Has Secret
            </div>
          )}
        </div>
      ),
    },
    {
      key: 'grants',
      label: 'Grant Types',
      render: (_, client) => (
        <div className="flex flex-wrap gap-1">
          {client.grants.map((grant) => (
            <Badge key={grant} variant="outline" className="text-xs">
              {grant.replace('_', ' ')}
            </Badge>
          ))}
        </div>
      ),
    },
    {
      key: 'scopes',
      label: 'Scopes',
      render: (_, client) => (
        <div className="flex flex-wrap gap-1">
          {client.scopes.slice(0, 3).map((scope) => (
            <Badge key={scope} variant="secondary" className="text-xs">
              {scope}
            </Badge>
          ))}
          {client.scopes.length > 3 && (
            <Badge variant="secondary" className="text-xs">
              +{client.scopes.length - 3} more
            </Badge>
          )}
        </div>
      ),
    },
    {
      key: 'tokens',
      label: 'Tokens',
      render: (_, client) => (
        <div className="text-center">
          <div className="text-sm font-medium">{client.access_tokens_count || 0}</div>
          <div className="text-xs text-muted-foreground">Active</div>
        </div>
      ),
    },
    {
      key: 'status',
      label: 'Status',
      render: (_, client) => (
        <div className="flex flex-col gap-2">
          <StatusBadge 
            status={client.is_active ? 'Active' : 'Inactive'}
            variant={client.is_active ? 'default' : 'secondary'}
          />
          <HealthIndicator 
            status={client.health_status}
            lastCheckedAt={client.last_health_check_at}
          />
          {client.maintenance_mode && (
            <Badge variant="outline" className="text-xs text-yellow-600 dark:text-yellow-400">
              <Settings className="h-3 w-3 mr-1" />
              Maintenance
            </Badge>
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
      render: (_, client) => (
        <div className="flex items-center gap-1">
          <ActionButton href={adminRoutes.oauthClients.show(client.id)} variant="ghost" title="View Details">
            <Eye className="h-4 w-4" />
          </ActionButton>
          <ActionButton href={adminRoutes.oauthClients.edit(client.id)} variant="ghost" title="Edit Client">
            <Edit className="h-4 w-4" />
          </ActionButton>
          {client.health_check_enabled && (
            <Button 
              variant="ghost" 
              size="sm"
              onClick={() => router.post(adminRoutes.oauthClients.healthCheck(client.id))}
              title="Run Health Check"
            >
              <RefreshCw className="h-4 w-4" />
            </Button>
          )}
          <AlertDialog>
            <AlertDialogTrigger asChild>
              <Button variant="ghost" size="sm">
                <Trash2 className="h-4 w-4" />
              </Button>
            </AlertDialogTrigger>
            <AlertDialogContent>
              <AlertDialogHeader>
                <AlertDialogTitle>Delete OAuth Client</AlertDialogTitle>
                <AlertDialogDescription>
                  Are you sure you want to delete the OAuth client <strong>{client.name}</strong>? This will revoke all associated tokens and cannot be undone.
                </AlertDialogDescription>
              </AlertDialogHeader>
              <AlertDialogFooter>
                <AlertDialogCancel>Cancel</AlertDialogCancel>
                <AlertDialogAction
                  onClick={() => router.delete(adminRoutes.oauthClients.destroy(client.id))}
                  className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                >
                  Delete Client
                </AlertDialogAction>
              </AlertDialogFooter>
            </AlertDialogContent>
          </AlertDialog>
        </div>
      ),
      className: "w-32",
    },
  ];

  const filtersComponent = (
    <div className="flex items-center gap-4">
      <Select 
        value={filters.status || 'all'} 
        onValueChange={(value) => handleFilter('status', value)}
      >
        <SelectTrigger className="w-32">
          <SelectValue placeholder="Status" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">All status</SelectItem>
          <SelectItem value="active">Active</SelectItem>
          <SelectItem value="inactive">Inactive</SelectItem>
        </SelectContent>
      </Select>

      <Select 
        value={filters.confidential || 'all'} 
        onValueChange={(value) => handleFilter('confidential', value)}
      >
        <SelectTrigger className="w-40">
          <SelectValue placeholder="Client Type" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">All types</SelectItem>
          <SelectItem value="true">Confidential</SelectItem>
          <SelectItem value="false">Public</SelectItem>
        </SelectContent>
      </Select>

      <Select 
        value={filters.health_status || 'all'} 
        onValueChange={(value) => handleFilter('health_status', value)}
      >
        <SelectTrigger className="w-36">
          <SelectValue placeholder="Health" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">All health</SelectItem>
          <SelectItem value="healthy">Healthy</SelectItem>
          <SelectItem value="unhealthy">Unhealthy</SelectItem>
          <SelectItem value="error">Error</SelectItem>
          <SelectItem value="unknown">Unknown</SelectItem>
        </SelectContent>
      </Select>

      <Select 
        value={filters.environment || 'all'} 
        onValueChange={(value) => handleFilter('environment', value)}
      >
        <SelectTrigger className="w-36">
          <SelectValue placeholder="Environment" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">All environments</SelectItem>
          <SelectItem value="production">Production</SelectItem>
          <SelectItem value="staging">Staging</SelectItem>
          <SelectItem value="development">Development</SelectItem>
        </SelectContent>
      </Select>
    </div>
  );

  const actionsComponent = (
    <Button asChild>
      <Link href={adminRoutes.oauthClients.create()}>
        <Plus className="h-4 w-4 mr-2" />
        Create Client
      </Link>
    </Button>
  );

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="OAuth Clients Management" />
      
      <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold tracking-tight">OAuth Clients</h1>
            <p className="text-muted-foreground">
              Manage OAuth2 clients and their configurations
            </p>
          </div>
        </div>

        {/* Stats */}
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
          <MetricCard
            title="Total Clients"
            value={stats.total}
            description={`${stats.active} active`}
            icon={Activity}
          />
          <MetricCard
            title="Health Status"
            value={stats.healthy}
            description={`${stats.unhealthy + (stats.total - stats.healthy - stats.unhealthy)} need attention`}
            trend={stats.healthy / stats.total > 0.8 ? 'up' : 'down'}
            icon={AlertTriangle}
          />
          <MetricCard
            title="Production"
            value={stats.production || 0}
            description={`${stats.staging || 0} staging, ${stats.development || 0} dev`}
            icon={Settings}
          />
          <MetricCard
            title="Maintenance"
            value={stats.maintenance || 0}
            description="Clients in maintenance mode"
            trend={stats.maintenance === 0 ? 'up' : 'neutral'}
            icon={Clock}
          />
        </div>

        {/* Quick Actions */}
        {(stats.unhealthy > 0 || stats.maintenance > 0) && (
          <Card className="border-yellow-200 bg-yellow-50 dark:border-yellow-700 dark:bg-yellow-950/50">
            <CardContent className="pt-6">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <AlertTriangle className="h-5 w-5 text-yellow-600 dark:text-yellow-400" />
                  <div>
                    <p className="font-medium text-yellow-800 dark:text-yellow-200">Attention Required</p>
                    <p className="text-sm text-yellow-600 dark:text-yellow-400">
                      {stats.unhealthy > 0 && `${stats.unhealthy} unhealthy clients`}
                      {stats.unhealthy > 0 && stats.maintenance > 0 && ', '}
                      {stats.maintenance > 0 && `${stats.maintenance} in maintenance`}
                    </p>
                  </div>
                </div>
                <div className="flex gap-2">
                  <Button variant="outline" size="sm" onClick={() => handleFilter('health_status', 'unhealthy')}>
                    View Unhealthy
                  </Button>
                  <Button variant="outline" size="sm" onClick={() => handleFilter('maintenance_mode', 'true')}>
                    View Maintenance
                  </Button>
                </div>
              </div>
            </CardContent>
          </Card>
        )}

        {/* Clients Table */}
        <DataTable
          data={clients.data}
          columns={columns}
          pagination={clients}
          searchable
          searchPlaceholder="Search clients..."
          onSearch={(query) => handleFilter('search', query)}
          filters={filtersComponent}
          actions={actionsComponent}
          emptyMessage="No OAuth clients found"
        />
      </div>
    </AppLayout>
  );
}