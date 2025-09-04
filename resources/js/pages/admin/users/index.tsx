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
import AppLayout from '@/layouts/app-layout';
import { adminRoutes } from '@/lib/admin-routes';
import { type BreadcrumbItem, type User, type PaginatedResponse } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, Eye, Edit, Trash2, RotateCcw } from 'lucide-react';

interface UsersIndexProps {
  users: PaginatedResponse<User>;
  filters: {
    search?: string;
    role?: string;
    status?: string;
    sort?: string;
    order?: string;
  };
  roles: string[];
  stats: {
    total: number;
    active: number;
    inactive: number;
    deleted: number;
    admins: number;
  };
}

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Admin', href: adminRoutes.dashboard() },
  { title: 'Users', href: adminRoutes.users.index() },
];

export default function UsersIndex({ users, filters, roles, stats }: UsersIndexProps) {

  const handleFilter = (key: keyof typeof filters, value: string) => {
    const newFilters = { ...filters, [key]: value };
    if (value === '' || value === 'all') {
      delete newFilters[key];
    }
    
    router.get(adminRoutes.users.index(), newFilters, {
      preserveState: true,
      preserveScroll: true,
    });
  };

  const handleDeleteUser = (user: User) => {
    router.delete(adminRoutes.users.destroy(user.id));
  };

  const columns: Column<User>[] = [
    {
      key: 'user',
      label: 'User',
      render: (_, user) => (
        <div className="flex items-center gap-3">
          <Avatar className="h-8 w-8">
            <AvatarImage src={user.avatar_url} alt={user.name} />
            <AvatarFallback>
              {user.name.split(' ').map(n => n[0]).join('').toUpperCase()}
            </AvatarFallback>
          </Avatar>
          <div className="flex flex-col">
            <span className="font-medium">{user.name}</span>
            <span className="text-sm text-muted-foreground">{user.email}</span>
          </div>
        </div>
      ),
    },
    {
      key: 'department',
      label: 'Department',
      render: (_, user) => (
        <div className="flex flex-col">
          {user.department && (
            <span className="text-sm font-medium">{user.department}</span>
          )}
          {user.job_title && (
            <span className="text-xs text-muted-foreground">{user.job_title}</span>
          )}
        </div>
      ),
    },
    {
      key: 'roles',
      label: 'Roles',
      render: (_, user) => (
        <div className="flex flex-wrap gap-1">
          {user.roles?.map((role) => (
            <Badge 
              key={role.name} 
              variant={
                role.name === 'super-admin' ? 'destructive' : 
                role.name === 'admin' ? 'default' : 'secondary'
              }
              className="text-xs"
            >
              {role.display_name || role.name}
            </Badge>
          )) || <span className="text-muted-foreground text-sm">No roles</span>}
        </div>
      ),
    },
    {
      key: 'status',
      label: 'Status',
      render: (_, user) => (
        <div className="flex flex-col gap-1">
          <StatusBadge 
            status={user.is_active ? 'Active' : 'Inactive'}
            variant={user.is_active ? 'default' : 'secondary'}
          />
          {user.deleted_at && (
            <StatusBadge status="Deleted" variant="destructive" />
          )}
        </div>
      ),
    },
    {
      key: 'last_login_at',
      label: 'Last Login',
      render: (value) => (
        <span className="text-sm text-muted-foreground">
          {value || 'Never'}
        </span>
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
      render: (_, user) => (
        <div className="flex items-center gap-1">
          <ActionButton href={adminRoutes.users.show(user.id)} variant="ghost">
            <Eye className="h-4 w-4" />
          </ActionButton>
          <ActionButton href={adminRoutes.users.edit(user.id)} variant="ghost">
            <Edit className="h-4 w-4" />
          </ActionButton>
          {user.deleted_at ? (
            <Button 
              variant="ghost" 
              size="sm"
              onClick={() => {
                router.post(adminRoutes.users.restore(user.id));
              }}
            >
              <RotateCcw className="h-4 w-4" />
            </Button>
          ) : (
            <AlertDialog>
              <AlertDialogTrigger asChild>
                <Button 
                  variant="ghost" 
                  size="sm"
                  disabled={user.is_super_admin}
                >
                  <Trash2 className="h-4 w-4" />
                </Button>
              </AlertDialogTrigger>
              <AlertDialogContent>
                <AlertDialogHeader>
                  <AlertDialogTitle>Delete User</AlertDialogTitle>
                  <AlertDialogDescription>
                    Are you sure you want to delete <strong>{user.name}</strong>? This action will soft delete the user and they can be restored later.
                  </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                  <AlertDialogCancel>Cancel</AlertDialogCancel>
                  <AlertDialogAction
                    onClick={() => handleDeleteUser(user)}
                    className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                  >
                    Delete User
                  </AlertDialogAction>
                </AlertDialogFooter>
              </AlertDialogContent>
            </AlertDialog>
          )}
        </div>
      ),
      className: "w-32",
    },
  ];

  const filtersComponent = (
    <div className="flex items-center gap-4">
      <Select 
        value={filters.role || 'all'} 
        onValueChange={(value) => handleFilter('role', value)}
      >
        <SelectTrigger className="w-40">
          <SelectValue placeholder="Filter by role" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">All roles</SelectItem>
          {roles.map((role) => (
            <SelectItem key={role} value={role}>
              {role.replace('-', ' ').replace(/\b\w/g, l => l.toUpperCase())}
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
          <SelectItem value="active">Active</SelectItem>
          <SelectItem value="inactive">Inactive</SelectItem>
          <SelectItem value="deleted">Deleted</SelectItem>
        </SelectContent>
      </Select>
    </div>
  );

  const actionsComponent = (
    <Button asChild>
      <Link href={adminRoutes.users.create()}>
        <Plus className="h-4 w-4 mr-2" />
        Add User
      </Link>
    </Button>
  );

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Users Management" />
      
      <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold tracking-tight">Users</h1>
            <p className="text-muted-foreground">
              Manage user accounts, roles, and permissions
            </p>
          </div>
        </div>

        {/* Stats */}
        <div className="grid gap-4 md:grid-cols-5">
          <div className="rounded-lg border p-4">
            <div className="text-2xl font-bold">{stats.total}</div>
            <div className="text-sm text-muted-foreground">Total Users</div>
          </div>
          <div className="rounded-lg border p-4">
            <div className="text-2xl font-bold text-green-600">{stats.active}</div>
            <div className="text-sm text-muted-foreground">Active</div>
          </div>
          <div className="rounded-lg border p-4">
            <div className="text-2xl font-bold text-gray-600">{stats.inactive}</div>
            <div className="text-sm text-muted-foreground">Inactive</div>
          </div>
          <div className="rounded-lg border p-4">
            <div className="text-2xl font-bold text-red-600">{stats.deleted}</div>
            <div className="text-sm text-muted-foreground">Deleted</div>
          </div>
          <div className="rounded-lg border p-4">
            <div className="text-2xl font-bold text-blue-600">{stats.admins}</div>
            <div className="text-sm text-muted-foreground">Admins</div>
          </div>
        </div>

        {/* Users Table */}
        <DataTable
          data={users.data}
          columns={columns}
          pagination={users}
          searchable
          searchPlaceholder="Search users..."
          onSearch={(query) => handleFilter('search', query)}
          filters={filtersComponent}
          actions={actionsComponent}
          emptyMessage="No users found"
        />
      </div>
    </AppLayout>
  );
}