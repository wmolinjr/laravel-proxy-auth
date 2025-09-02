import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { adminRoutes } from '@/lib/admin-routes';
import { type BreadcrumbItem, type User, type OAuthToken, type AuditLog } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { 
  Edit, 
  Mail, 
  Phone, 
  MapPin, 
  Calendar, 
  Clock, 
  Shield, 
  Key, 
  Activity,
  AlertCircle,
  CheckCircle,
  XCircle
} from 'lucide-react';

interface UserShowProps {
  user: User & {
    full_name: string;
    needs_password_change: boolean;
    requires_2fa: boolean;
    active_tokens_count: number;
  };
  recentTokens: OAuthToken[];
  auditLogs: AuditLog[];
}

const breadcrumbs = (user: User): BreadcrumbItem[] => [
  { title: 'Admin', href: adminRoutes.dashboard() },
  { title: 'Users', href: adminRoutes.users.index() },
  { title: user.name, href: adminRoutes.users.show(user.id) },
];

export default function UserShow({ user, recentTokens, auditLogs }: UserShowProps) {
  return (
    <AppLayout breadcrumbs={breadcrumbs(user)}>
      <Head title={`User: ${user.name}`} />
      
      <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6">
        {/* Header */}
        <div className="flex items-start justify-between">
          <div className="flex items-center gap-4">
            <Avatar className="h-16 w-16">
              <AvatarImage src={user.avatar_url} alt={user.name} />
              <AvatarFallback className="text-lg">
                {user.name.split(' ').map(n => n[0]).join('').toUpperCase()}
              </AvatarFallback>
            </Avatar>
            <div>
              <h1 className="text-3xl font-bold tracking-tight">{user.full_name}</h1>
              <p className="text-lg text-muted-foreground">{user.email}</p>
              <div className="flex items-center gap-2 mt-2">
                {user.is_active ? (
                  <Badge variant="default">
                    <CheckCircle className="h-3 w-3 mr-1" />
                    Active
                  </Badge>
                ) : (
                  <Badge variant="secondary">
                    <XCircle className="h-3 w-3 mr-1" />
                    Inactive
                  </Badge>
                )}
                
                {user.deleted_at && (
                  <Badge variant="destructive">Deleted</Badge>
                )}
                
                {user.is_super_admin && (
                  <Badge variant="destructive">
                    <Shield className="h-3 w-3 mr-1" />
                    Super Admin
                  </Badge>
                )}
                
                {user.is_admin && !user.is_super_admin && (
                  <Badge variant="default">
                    <Shield className="h-3 w-3 mr-1" />
                    Admin
                  </Badge>
                )}
              </div>
            </div>
          </div>
          
          <div className="flex items-center gap-2">
            <Button variant="outline" asChild>
              <Link href={adminRoutes.users.edit(user.id)}>
                <Edit className="h-4 w-4 mr-2" />
                Edit User
              </Link>
            </Button>
          </div>
        </div>

        {/* Alerts */}
        {(user.needs_password_change || user.requires_2fa || !user.is_active) && (
          <div className="space-y-2">
            {user.needs_password_change && (
              <div className="flex items-center gap-2 p-3 border border-orange-200 bg-orange-50 rounded-lg">
                <AlertCircle className="h-4 w-4 text-orange-600" />
                <span className="text-sm text-orange-800">User needs to change password</span>
              </div>
            )}
            
            {user.requires_2fa && !user.two_factor_enabled && (
              <div className="flex items-center gap-2 p-3 border border-blue-200 bg-blue-50 rounded-lg">
                <AlertCircle className="h-4 w-4 text-blue-600" />
                <span className="text-sm text-blue-800">Two-factor authentication required but not enabled</span>
              </div>
            )}
            
            {!user.is_active && (
              <div className="flex items-center gap-2 p-3 border border-red-200 bg-red-50 rounded-lg">
                <XCircle className="h-4 w-4 text-red-600" />
                <span className="text-sm text-red-800">Account is deactivated</span>
              </div>
            )}
          </div>
        )}

        <Tabs defaultValue="overview" className="space-y-6">
          <TabsList className="grid w-full grid-cols-4">
            <TabsTrigger value="overview">Overview</TabsTrigger>
            <TabsTrigger value="tokens">Tokens ({user.active_tokens_count})</TabsTrigger>
            <TabsTrigger value="activity">Activity</TabsTrigger>
            <TabsTrigger value="security">Security</TabsTrigger>
          </TabsList>

          <TabsContent value="overview" className="space-y-6">
            <div className="grid gap-6 lg:grid-cols-2">
              {/* User Information */}
              <Card>
                <CardHeader>
                  <CardTitle>User Information</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="space-y-3">
                    <div className="flex items-center gap-2">
                      <Mail className="h-4 w-4 text-muted-foreground" />
                      <span className="text-sm">{user.email}</span>
                    </div>
                    
                    {user.phone && (
                      <div className="flex items-center gap-2">
                        <Phone className="h-4 w-4 text-muted-foreground" />
                        <span className="text-sm">{user.phone}</span>
                      </div>
                    )}
                    
                    {user.department && (
                      <div className="flex items-center gap-2">
                        <MapPin className="h-4 w-4 text-muted-foreground" />
                        <span className="text-sm">{user.department}</span>
                        {user.job_title && (
                          <span className="text-sm text-muted-foreground">- {user.job_title}</span>
                        )}
                      </div>
                    )}
                    
                    <div className="flex items-center gap-2">
                      <Calendar className="h-4 w-4 text-muted-foreground" />
                      <span className="text-sm">Joined {user.created_at}</span>
                    </div>
                    
                    {user.last_login_at && (
                      <div className="flex items-center gap-2">
                        <Clock className="h-4 w-4 text-muted-foreground" />
                        <span className="text-sm">Last login {user.last_login_at}</span>
                      </div>
                    )}
                  </div>
                </CardContent>
              </Card>

              {/* Roles & Permissions */}
              <Card>
                <CardHeader>
                  <CardTitle>Roles & Permissions</CardTitle>
                </CardHeader>
                <CardContent>
                  {user.roles && user.roles.length > 0 ? (
                    <div className="space-y-3">
                      {user.roles.map((role) => (
                        <div key={role.id} className="flex items-center justify-between p-3 border rounded-lg">
                          <div>
                            <div className="font-medium">{role.display_name}</div>
                            <div className="text-sm text-muted-foreground">{role.name}</div>
                          </div>
                          <Badge variant={
                            role.name === 'super-admin' ? 'destructive' : 
                            role.name === 'admin' ? 'default' : 'secondary'
                          }>
                            {role.name}
                          </Badge>
                        </div>
                      ))}
                    </div>
                  ) : (
                    <p className="text-sm text-muted-foreground">No roles assigned</p>
                  )}
                </CardContent>
              </Card>
            </div>
          </TabsContent>

          <TabsContent value="tokens" className="space-y-6">
            <Card>
              <CardHeader>
                <CardTitle>Recent OAuth Tokens</CardTitle>
              </CardHeader>
              <CardContent>
                {recentTokens.length > 0 ? (
                  <div className="space-y-4">
                    {recentTokens.map((token) => (
                      <div key={token.id} className="flex items-center justify-between p-4 border rounded-lg">
                        <div className="space-y-1">
                          <div className="font-medium">
                            {token.client?.name || 'Unknown Client'}
                          </div>
                          <div className="text-sm text-muted-foreground">
                            {token.client?.identifier}
                          </div>
                          <div className="flex items-center gap-2 text-xs text-muted-foreground">
                            <span>Expires: {token.expires_at}</span>
                            <span>•</span>
                            <span>Created: {token.created_at}</span>
                          </div>
                          {token.scopes && token.scopes.length > 0 && (
                            <div className="flex flex-wrap gap-1 mt-2">
                              {token.scopes.map((scope) => (
                                <Badge key={scope} variant="outline" className="text-xs">
                                  {scope}
                                </Badge>
                              ))}
                            </div>
                          )}
                        </div>
                        <div className="flex flex-col items-end gap-2">
                          <Badge variant={
                            token.is_valid ? 'default' :
                            token.is_expired ? 'secondary' : 'destructive'
                          }>
                            {token.is_valid ? 'Valid' : 
                             token.is_expired ? 'Expired' : 'Revoked'}
                          </Badge>
                        </div>
                      </div>
                    ))}
                  </div>
                ) : (
                  <p className="text-center text-muted-foreground py-8">
                    No OAuth tokens found for this user
                  </p>
                )}
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="activity" className="space-y-6">
            <Card>
              <CardHeader>
                <CardTitle>Activity Log</CardTitle>
              </CardHeader>
              <CardContent>
                {auditLogs.length > 0 ? (
                  <div className="space-y-4">
                    {auditLogs.map((log) => (
                      <div key={log.id} className="flex items-start gap-4 pb-4 border-b last:border-0">
                        <div className="w-2 h-2 bg-primary rounded-full mt-2 shrink-0"></div>
                        <div className="flex-1 space-y-1">
                          <div className="font-medium">
                            {log.event_type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}
                          </div>
                          <div className="text-sm text-muted-foreground">
                            {log.created_at}
                          </div>
                          {log.user && (
                            <div className="text-xs text-muted-foreground">
                              by {log.user.name} ({log.user.email})
                            </div>
                          )}
                          {log.ip_address && (
                            <div className="text-xs text-muted-foreground">
                              from {log.ip_address}
                            </div>
                          )}
                        </div>
                      </div>
                    ))}
                  </div>
                ) : (
                  <p className="text-center text-muted-foreground py-8">
                    No activity logs found for this user
                  </p>
                )}
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="security" className="space-y-6">
            <div className="grid gap-6 lg:grid-cols-2">
              <Card>
                <CardHeader>
                  <CardTitle>Security Status</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="flex items-center justify-between">
                    <span>Two-Factor Authentication</span>
                    <Badge variant={user.two_factor_enabled ? 'default' : 'secondary'}>
                      {user.two_factor_enabled ? 'Enabled' : 'Disabled'}
                    </Badge>
                  </div>
                  
                  <Separator />
                  
                  <div className="flex items-center justify-between">
                    <span>Password Last Changed</span>
                    <span className="text-sm text-muted-foreground">
                      {user.password_changed_at || 'Never'}
                    </span>
                  </div>
                  
                  <div className="flex items-center justify-between">
                    <span>Account Status</span>
                    <Badge variant={user.is_active ? 'default' : 'destructive'}>
                      {user.is_active ? 'Active' : 'Suspended'}
                    </Badge>
                  </div>
                  
                  <div className="flex items-center justify-between">
                    <span>Email Verified</span>
                    <Badge variant={user.email_verified_at ? 'default' : 'secondary'}>
                      {user.email_verified_at ? 'Verified' : 'Unverified'}
                    </Badge>
                  </div>
                </CardContent>
              </Card>

              <Card>
                <CardHeader>
                  <CardTitle>Security Metrics</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="flex items-center justify-between">
                    <span>Active OAuth Tokens</span>
                    <Badge variant="outline">
                      {user.active_tokens_count}
                    </Badge>
                  </div>
                  
                  <div className="flex items-center justify-between">
                    <span>Admin Privileges</span>
                    <Badge variant={user.is_admin ? 'default' : 'secondary'}>
                      {user.is_admin ? 'Yes' : 'No'}
                    </Badge>
                  </div>
                </CardContent>
              </Card>
            </div>
          </TabsContent>
        </Tabs>
      </div>
    </AppLayout>
  );
}