import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { adminRoutes } from '@/lib/admin-routes';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, Link } from '@inertiajs/react';
import { ArrowLeft, Save, User } from 'lucide-react';

interface Role {
  value: string;
  label: string;
}

interface UserData {
  id: number;
  name: string;
  email: string;
  department: string;
  job_title: string;
  phone: string;
  timezone: string;
  locale: string;
  is_active: boolean;
  roles: string[];
}

interface EditUserProps {
  user: UserData;
  roles: Role[];
}

export default function EditUser({ user, roles }: EditUserProps) {
  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: adminRoutes.dashboard() },
    { title: 'Users', href: adminRoutes.users.index() },
    { title: `Edit ${user.name}` },
  ];

  const { data, setData, put, processing, errors } = useForm({
    name: user.name,
    email: user.email,
    password: '',
    password_confirmation: '',
    department: user.department || '',
    job_title: user.job_title || '',
    phone: user.phone || '',
    timezone: user.timezone || 'America/Sao_Paulo',
    locale: user.locale || 'pt_BR',
    is_active: user.is_active,
    roles: user.roles || [],
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    put(adminRoutes.users.update(user.id));
  };

  const handleRoleToggle = (roleValue: string) => {
    if (data.roles.includes(roleValue)) {
      setData('roles', data.roles.filter(role => role !== roleValue));
    } else {
      setData('roles', [...data.roles, roleValue]);
    }
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={`Edit ${user.name}`} />
      
      <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-4">
            <Link href={adminRoutes.users.index()}>
              <Button variant="outline" size="icon">
                <ArrowLeft className="h-4 w-4" />
              </Button>
            </Link>
            <div>
              <h1 className="text-3xl font-bold tracking-tight">Edit User</h1>
              <p className="text-muted-foreground">
                Update user information and permissions
              </p>
            </div>
          </div>
        </div>

        <form onSubmit={handleSubmit} className="space-y-6">
          <div className="grid gap-6 lg:grid-cols-2">
            {/* Basic Information */}
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <User className="h-5 w-5" />
                  Basic Information
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="space-y-2">
                  <Label htmlFor="name">Name *</Label>
                  <Input
                    id="name"
                    type="text"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    error={errors.name}
                    required
                  />
                  {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="email">Email *</Label>
                  <Input
                    id="email"
                    type="email"
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    error={errors.email}
                    required
                  />
                  {errors.email && <p className="text-sm text-destructive">{errors.email}</p>}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="password">New Password</Label>
                  <Input
                    id="password"
                    type="password"
                    value={data.password}
                    onChange={(e) => setData('password', e.target.value)}
                    error={errors.password}
                    placeholder="Leave blank to keep current password"
                  />
                  {errors.password && <p className="text-sm text-destructive">{errors.password}</p>}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="password_confirmation">Confirm New Password</Label>
                  <Input
                    id="password_confirmation"
                    type="password"
                    value={data.password_confirmation}
                    onChange={(e) => setData('password_confirmation', e.target.value)}
                    error={errors.password_confirmation}
                    placeholder="Confirm new password"
                  />
                  {errors.password_confirmation && (
                    <p className="text-sm text-destructive">{errors.password_confirmation}</p>
                  )}
                </div>

                <div className="flex items-center space-x-2">
                  <Switch
                    id="is_active"
                    checked={data.is_active}
                    onCheckedChange={(checked) => setData('is_active', checked)}
                  />
                  <Label htmlFor="is_active">Active User</Label>
                </div>
              </CardContent>
            </Card>

            {/* Additional Information */}
            <Card>
              <CardHeader>
                <CardTitle>Additional Information</CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="space-y-2">
                  <Label htmlFor="department">Department</Label>
                  <Input
                    id="department"
                    type="text"
                    value={data.department}
                    onChange={(e) => setData('department', e.target.value)}
                  />
                </div>

                <div className="space-y-2">
                  <Label htmlFor="job_title">Job Title</Label>
                  <Input
                    id="job_title"
                    type="text"
                    value={data.job_title}
                    onChange={(e) => setData('job_title', e.target.value)}
                  />
                </div>

                <div className="space-y-2">
                  <Label htmlFor="phone">Phone</Label>
                  <Input
                    id="phone"
                    type="tel"
                    value={data.phone}
                    onChange={(e) => setData('phone', e.target.value)}
                  />
                </div>

                <div className="space-y-2">
                  <Label htmlFor="timezone">Timezone</Label>
                  <Input
                    id="timezone"
                    type="text"
                    value={data.timezone}
                    onChange={(e) => setData('timezone', e.target.value)}
                  />
                </div>
              </CardContent>
            </Card>
          </div>

          {/* Roles */}
          <Card>
            <CardHeader>
              <CardTitle>User Roles</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                {roles.map((role) => (
                  <div key={role.value} className="flex items-center space-x-2">
                    <Switch
                      id={role.value}
                      checked={data.roles.includes(role.value)}
                      onCheckedChange={() => handleRoleToggle(role.value)}
                    />
                    <Label htmlFor={role.value} className="flex items-center gap-2">
                      {role.label}
                      {data.roles.includes(role.value) && (
                        <Badge variant="secondary" className="text-xs">
                          Selected
                        </Badge>
                      )}
                    </Label>
                  </div>
                ))}
              </div>
              {errors.roles && <p className="text-sm text-destructive mt-2">{errors.roles}</p>}
            </CardContent>
          </Card>

          {/* Actions */}
          <div className="flex items-center justify-end gap-4">
            <Link href={adminRoutes.users.index()}>
              <Button variant="outline">Cancel</Button>
            </Link>
            <Button type="submit" disabled={processing}>
              <Save className="h-4 w-4 mr-2" />
              {processing ? 'Updating...' : 'Update User'}
            </Button>
          </div>
        </form>
      </div>
    </AppLayout>
  );
}