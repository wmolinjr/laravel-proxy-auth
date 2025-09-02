import { DataTable, Column, StatusBadge, ActionButton } from '@/components/ui/data-table';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
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
import { Plus, Eye, Edit, Trash2, Key } from 'lucide-react';

interface OAuthClientsIndexProps {
  clients: PaginatedResponse<OAuthClient>;
  filters: {
    search?: string;
    status?: string;
    confidential?: string;
    sort?: string;
    order?: string;
  };
  stats: {
    total: number;
    active: number;
    confidential: number;
    public: number;
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
        <StatusBadge 
          status={client.is_active ? 'Active' : 'Inactive'}
          variant={client.is_active ? 'default' : 'secondary'}
        />
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
          <ActionButton href={adminRoutes.oauthClients.show(client.id)} variant="ghost">
            <Eye className="h-4 w-4" />
          </ActionButton>
          <ActionButton href={adminRoutes.oauthClients.edit(client.id)} variant="ghost">
            <Edit className="h-4 w-4" />
          </ActionButton>
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
        <div className="grid gap-4 md:grid-cols-4">
          <div className="rounded-lg border p-4">
            <div className="text-2xl font-bold">{stats.total}</div>
            <div className="text-sm text-muted-foreground">Total Clients</div>
          </div>
          <div className="rounded-lg border p-4">
            <div className="text-2xl font-bold text-green-600">{stats.active}</div>
            <div className="text-sm text-muted-foreground">Active</div>
          </div>
          <div className="rounded-lg border p-4">
            <div className="text-2xl font-bold text-blue-600">{stats.confidential}</div>
            <div className="text-sm text-muted-foreground">Confidential</div>
          </div>
          <div className="rounded-lg border p-4">
            <div className="text-2xl font-bold text-gray-600">{stats.public}</div>
            <div className="text-sm text-muted-foreground">Public</div>
          </div>
        </div>

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