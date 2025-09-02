import { DataTable, Column, StatusBadge, ActionButton } from '@/components/ui/data-table';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
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
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog";
import AppLayout from '@/layouts/app-layout';
import { adminRoutes } from '@/lib/admin-routes';
import { type BreadcrumbItem, type OAuthToken, type PaginatedResponse, type OAuthClient, type User } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Eye, Trash2, Clock, CheckCircle, XCircle } from 'lucide-react';
import { useState } from 'react';

interface TokensIndexProps {
  tokens: PaginatedResponse<OAuthToken>;
  filters: {
    search?: string;
    client?: string;
    user?: string;
    status?: string;
    scope?: string;
    sort?: string;
    order?: string;
  };
  clients: OAuthClient[];
  users: User[];
  availableScopes: string[];
  stats: {
    total: number;
    valid: number;
    expired: number;
    revoked: number;
    issued_today: number;
  };
}

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Admin', href: adminRoutes.dashboard() },
  { title: 'Tokens', href: adminRoutes.tokens.index() },
];

export default function TokensIndex({ 
  tokens, 
  filters, 
  clients, 
  availableScopes, 
  stats 
}: TokensIndexProps) {
  const [bulkActionDialog, setBulkActionDialog] = useState(false);
  const [selectedTokens, setSelectedTokens] = useState<number[]>([]);

  const handleFilter = (key: keyof typeof filters, value: string) => {
    const newFilters = { ...filters, [key]: value };
    if (value === '' || value === 'all') {
      delete newFilters[key];
    }
    
    router.get(adminRoutes.tokens.index(), newFilters, {
      preserveState: true,
      preserveScroll: true,
    });
  };

  const handleBulkRevoke = () => {
    if (selectedTokens.length === 0) return;
    
    router.post(adminRoutes.tokens.revoke(), {
      token_ids: selectedTokens
    }, {
      onSuccess: () => {
        setSelectedTokens([]);
        setBulkActionDialog(false);
      }
    });
  };

  const columns: Column<OAuthToken>[] = [
    {
      key: 'select',
      label: 'Select',
      render: (_, token) => (
        <input
          type="checkbox"
          checked={selectedTokens.includes(token.id)}
          onChange={(e) => {
            if (e.target.checked) {
              setSelectedTokens([...selectedTokens, token.id]);
            } else {
              setSelectedTokens(selectedTokens.filter(id => id !== token.id));
            }
          }}
          className="rounded border-gray-300"
        />
      ),
      className: "w-12",
    },
    {
      key: 'user',
      label: 'User',
      render: (_, token) => (
        token.user ? (
          <div className="flex items-center gap-2">
            <Avatar className="h-6 w-6">
              <AvatarImage src={token.user.avatar_url} alt={token.user.name} />
              <AvatarFallback className="text-xs">
                {token.user.name.split(' ').map(n => n[0]).join('').toUpperCase()}
              </AvatarFallback>
            </Avatar>
            <div className="flex flex-col">
              <span className="text-sm font-medium">{token.user.name}</span>
              <span className="text-xs text-muted-foreground">{token.user.email}</span>
            </div>
          </div>
        ) : (
          <span className="text-sm text-muted-foreground">System Token</span>
        )
      ),
    },
    {
      key: 'client',
      label: 'Client',
      render: (_, token) => (
        token.client ? (
          <div className="flex flex-col">
            <span className="text-sm font-medium">{token.client.name}</span>
            <span className="text-xs text-muted-foreground font-mono">
              {token.client.identifier}
            </span>
          </div>
        ) : (
          <span className="text-sm text-muted-foreground">Unknown</span>
        )
      ),
    },
    {
      key: 'scopes',
      label: 'Scopes',
      render: (_, token) => (
        <div className="flex flex-wrap gap-1">
          {token.scopes.slice(0, 2).map((scope) => (
            <Badge key={scope} variant="secondary" className="text-xs">
              {scope}
            </Badge>
          ))}
          {token.scopes.length > 2 && (
            <Badge variant="secondary" className="text-xs">
              +{token.scopes.length - 2}
            </Badge>
          )}
        </div>
      ),
    },
    {
      key: 'status',
      label: 'Status',
      render: (_, token) => (
        <div className="flex items-center gap-2">
          {token.is_valid ? (
            <>
              <CheckCircle className="h-4 w-4 text-green-600" />
              <StatusBadge status="Valid" variant="default" />
            </>
          ) : token.is_expired ? (
            <>
              <Clock className="h-4 w-4 text-orange-600" />
              <StatusBadge status="Expired" variant="secondary" />
            </>
          ) : (
            <>
              <XCircle className="h-4 w-4 text-red-600" />
              <StatusBadge status="Revoked" variant="destructive" />
            </>
          )}
        </div>
      ),
    },
    {
      key: 'expires_at',
      label: 'Expires',
      render: (_, token) => (
        <div className="flex flex-col">
          <span className="text-sm">{token.expires_at || 'Never'}</span>
          {token.time_until_expiry && (
            <span className="text-xs text-muted-foreground">
              {token.time_until_expiry}
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
      render: (_, token) => (
        <div className="flex items-center gap-1">
          <ActionButton href={adminRoutes.tokens.show(token.id)} variant="ghost">
            <Eye className="h-4 w-4" />
          </ActionButton>
          {!token.is_revoked && (
            <AlertDialog>
              <AlertDialogTrigger asChild>
                <Button variant="ghost" size="sm">
                  <Trash2 className="h-4 w-4" />
                </Button>
              </AlertDialogTrigger>
              <AlertDialogContent>
                <AlertDialogHeader>
                  <AlertDialogTitle>Revoke Token</AlertDialogTitle>
                  <AlertDialogDescription>
                    Are you sure you want to revoke this access token? This action cannot be undone and will immediately invalidate the token.
                  </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                  <AlertDialogCancel>Cancel</AlertDialogCancel>
                  <AlertDialogAction
                    onClick={() => router.delete(adminRoutes.tokens.destroy(token.id))}
                    className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                  >
                    Revoke Token
                  </AlertDialogAction>
                </AlertDialogFooter>
              </AlertDialogContent>
            </AlertDialog>
          )}
        </div>
      ),
      className: "w-24",
    },
  ];

  const filtersComponent = (
    <div className="flex items-center gap-4">
      <Select 
        value={filters.client || 'all'} 
        onValueChange={(value) => handleFilter('client', value)}
      >
        <SelectTrigger className="w-48">
          <SelectValue placeholder="Filter by client" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">All clients</SelectItem>
          {clients.map((client) => (
            <SelectItem key={client.id} value={client.id.toString()}>
              {client.name}
            </SelectItem>
          ))}
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
          <SelectItem value="valid">Valid</SelectItem>
          <SelectItem value="expired">Expired</SelectItem>
          <SelectItem value="revoked">Revoked</SelectItem>
        </SelectContent>
      </Select>

      <Select 
        value={filters.scope || 'all'} 
        onValueChange={(value) => handleFilter('scope', value)}
      >
        <SelectTrigger className="w-32">
          <SelectValue placeholder="Scope" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">All scopes</SelectItem>
          {availableScopes.map((scope) => (
            <SelectItem key={scope} value={scope}>
              {scope}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
    </div>
  );

  const actionsComponent = (
    <div className="flex items-center gap-2">
      {selectedTokens.length > 0 && (
        <Dialog open={bulkActionDialog} onOpenChange={setBulkActionDialog}>
          <DialogTrigger asChild>
            <Button variant="destructive" size="sm">
              <Trash2 className="h-4 w-4 mr-2" />
              Revoke Selected ({selectedTokens.length})
            </Button>
          </DialogTrigger>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>Revoke Tokens</DialogTitle>
              <DialogDescription>
                Are you sure you want to revoke {selectedTokens.length} selected tokens? 
                This action cannot be undone.
              </DialogDescription>
            </DialogHeader>
            <DialogFooter>
              <Button variant="outline" onClick={() => setBulkActionDialog(false)}>
                Cancel
              </Button>
              <Button variant="destructive" onClick={handleBulkRevoke}>
                Revoke Tokens
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      )}
    </div>
  );

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="OAuth Tokens Management" />
      
      <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold tracking-tight">OAuth Tokens</h1>
            <p className="text-muted-foreground">
              Monitor and manage OAuth access tokens
            </p>
          </div>
        </div>

        {/* Stats */}
        <div className="grid gap-4 md:grid-cols-5">
          <div className="rounded-lg border p-4">
            <div className="text-2xl font-bold">{stats.total}</div>
            <div className="text-sm text-muted-foreground">Total Tokens</div>
          </div>
          <div className="rounded-lg border p-4">
            <div className="text-2xl font-bold text-green-600">{stats.valid}</div>
            <div className="text-sm text-muted-foreground">Valid</div>
          </div>
          <div className="rounded-lg border p-4">
            <div className="text-2xl font-bold text-orange-600">{stats.expired}</div>
            <div className="text-sm text-muted-foreground">Expired</div>
          </div>
          <div className="rounded-lg border p-4">
            <div className="text-2xl font-bold text-red-600">{stats.revoked}</div>
            <div className="text-sm text-muted-foreground">Revoked</div>
          </div>
          <div className="rounded-lg border p-4">
            <div className="text-2xl font-bold text-blue-600">{stats.issued_today}</div>
            <div className="text-sm text-muted-foreground">Today</div>
          </div>
        </div>

        {/* Tokens Table */}
        <DataTable
          data={tokens.data}
          columns={columns}
          pagination={tokens}
          searchable
          searchPlaceholder="Search tokens..."
          onSearch={(query) => handleFilter('search', query)}
          filters={filtersComponent}
          actions={actionsComponent}
          emptyMessage="No OAuth tokens found"
        />
      </div>
    </AppLayout>
  );
}