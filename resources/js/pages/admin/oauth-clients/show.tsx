import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
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
} from '@/components/ui/alert-dialog';
import { HealthStatus, HealthIndicator } from '@/components/ui/health-status';
import { UsageMetrics, RecentEvents, EventSeverityBadge } from '@/components/ui/monitoring-metrics';
import AppLayout from '@/layouts/app-layout';
import { adminRoutes } from '@/lib/admin-routes';
import { type BreadcrumbItem, type OAuthClient, type OAuthClientUsage, type OAuthClientEvent } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { 
  Edit, 
  Trash2, 
  Key, 
  RefreshCw, 
  Settings, 
  Globe, 
  Shield, 
  Activity, 
  AlertTriangle, 
  Clock,
  ExternalLink,
  Copy,
  CheckCircle,
  XCircle,
  PlayCircle,
  PauseCircle
} from 'lucide-react';
import { useState } from 'react';

interface OAuthClientShowProps {
  client: OAuthClient;
  usage?: OAuthClientUsage[];
  events?: OAuthClientEvent[];
  stats: {
    total_tokens: number;
    active_tokens: number;
    total_authorizations: number;
    success_rate: number;
    avg_response_time: number;
    last_activity: string;
  };
}

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Admin', href: adminRoutes.dashboard() },
  { title: 'OAuth Clients', href: adminRoutes.oauthClients.index() },
];

export default function OAuthClientShow({ client, usage = [], events = [], stats }: OAuthClientShowProps) {
  const [copiedField, setCopiedField] = useState<string | null>(null);

  const copyToClipboard = async (text: string, field: string) => {
    await navigator.clipboard.writeText(text);
    setCopiedField(field);
    setTimeout(() => setCopiedField(null), 2000);
  };

  const currentBreadcrumbs = [
    ...breadcrumbs,
    { title: client.name, href: adminRoutes.oauthClients.show(client.id) },
  ];

  const handleToggleStatus = () => {
    router.post(adminRoutes.oauthClients.toggleStatus(client.id));
  };

  const handleToggleMaintenance = () => {
    router.post(adminRoutes.oauthClients.toggleMaintenance(client.id));
  };

  const handleHealthCheck = () => {
    router.post(adminRoutes.oauthClients.healthCheck(client.id));
  };

  const latestUsage = usage.length > 0 ? usage[0] : undefined;

  return (
    <AppLayout breadcrumbs={currentBreadcrumbs}>
      <Head title={`OAuth Client - ${client.name}`} />
      
      <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6">
        {/* Header */}
        <div className="flex items-start justify-between">
          <div className="space-y-1">
            <div className="flex items-center gap-3">
              <h1 className="text-3xl font-bold tracking-tight">{client.name}</h1>
              <HealthStatus 
                status={client.health_status}
                size="lg"
              />
              {client.maintenance_mode && (
                <Badge variant="outline" className="text-yellow-600">
                  <Settings className="h-4 w-4 mr-1" />
                  Maintenance Mode
                </Badge>
              )}
            </div>
            {client.description && (
              <p className="text-lg text-muted-foreground">{client.description}</p>
            )}
            <div className="flex items-center gap-4 text-sm text-muted-foreground">
              <span>ID: {client.identifier}</span>
              <span>•</span>
              <span className="capitalize">{client.environment}</span>
              <span>•</span>
              <span>Created {new Date(client.created_at).toLocaleDateString()}</span>
            </div>
          </div>
          
          <div className="flex items-center gap-2">
            <Button
              variant="outline"
              onClick={handleToggleStatus}
              className={client.is_active ? "text-red-600" : "text-green-600"}
            >
              {client.is_active ? <PauseCircle className="h-4 w-4 mr-2" /> : <PlayCircle className="h-4 w-4 mr-2" />}
              {client.is_active ? 'Deactivate' : 'Activate'}
            </Button>
            
            <Button
              variant="outline"
              onClick={handleToggleMaintenance}
            >
              <Settings className="h-4 w-4 mr-2" />
              {client.maintenance_mode ? 'Exit Maintenance' : 'Enter Maintenance'}
            </Button>
            
            {client.health_check_enabled && (
              <Button variant="outline" onClick={handleHealthCheck}>
                <RefreshCw className="h-4 w-4 mr-2" />
                Health Check
              </Button>
            )}
            
            <Button asChild>
              <Link href={adminRoutes.oauthClients.edit(client.id)}>
                <Edit className="h-4 w-4 mr-2" />
                Edit
              </Link>
            </Button>
          </div>
        </div>

        {/* Quick Stats */}
        <UsageMetrics usage={latestUsage} />

        <Tabs defaultValue="overview" className="space-y-6">
          <TabsList>
            <TabsTrigger value="overview">Overview</TabsTrigger>
            <TabsTrigger value="configuration">Configuration</TabsTrigger>
            <TabsTrigger value="monitoring">Monitoring</TabsTrigger>
            <TabsTrigger value="events">Events</TabsTrigger>
            <TabsTrigger value="tokens">Tokens</TabsTrigger>
          </TabsList>

          <TabsContent value="overview" className="space-y-6">
            <div className="grid gap-6 md:grid-cols-2">
              {/* Client Information */}
              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <Globe className="h-5 w-5" />
                    Client Information
                  </CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div>
                    <label className="text-sm font-medium">Client ID</label>
                    <div className="flex items-center gap-2 mt-1">
                      <code className="flex-1 px-2 py-1 bg-muted rounded text-sm font-mono">
                        {client.identifier}
                      </code>
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => copyToClipboard(client.identifier, 'client_id')}
                      >
                        {copiedField === 'client_id' ? <CheckCircle className="h-4 w-4" /> : <Copy className="h-4 w-4" />}
                      </Button>
                    </div>
                  </div>

                  <div>
                    <label className="text-sm font-medium">Type</label>
                    <div className="mt-1">
                      <Badge variant={client.is_confidential ? 'default' : 'secondary'}>
                        {client.is_confidential ? 'Confidential' : 'Public'}
                      </Badge>
                    </div>
                  </div>

                  <div>
                    <label className="text-sm font-medium">Status</label>
                    <div className="flex items-center gap-2 mt-1">
                      <Badge variant={client.is_active ? 'default' : 'secondary'}>
                        {client.is_active ? 'Active' : 'Inactive'}
                      </Badge>
                      {client.has_secret && (
                        <Badge variant="outline" className="text-xs">
                          <Key className="h-3 w-3 mr-1" />
                          Has Secret
                        </Badge>
                      )}
                    </div>
                  </div>

                  {(client.website_url || client.documentation_url) && (
                    <div>
                      <label className="text-sm font-medium">Links</label>
                      <div className="flex flex-col gap-1 mt-1">
                        {client.website_url && (
                          <a 
                            href={client.website_url} 
                            target="_blank" 
                            rel="noopener noreferrer"
                            className="text-sm text-blue-600 hover:text-blue-800 flex items-center gap-1"
                          >
                            Website <ExternalLink className="h-3 w-3" />
                          </a>
                        )}
                        {client.documentation_url && (
                          <a 
                            href={client.documentation_url} 
                            target="_blank" 
                            rel="noopener noreferrer"
                            className="text-sm text-blue-600 hover:text-blue-800 flex items-center gap-1"
                          >
                            Documentation <ExternalLink className="h-3 w-3" />
                          </a>
                        )}
                      </div>
                    </div>
                  )}

                  {(client.owner_contact || client.technical_contact) && (
                    <div>
                      <label className="text-sm font-medium">Contacts</label>
                      <div className="space-y-1 mt-1 text-sm">
                        {client.owner_contact && (
                          <div>Owner: {client.owner_contact}</div>
                        )}
                        {client.technical_contact && (
                          <div>Technical: {client.technical_contact}</div>
                        )}
                      </div>
                    </div>
                  )}
                </CardContent>
              </Card>

              {/* Health Status */}
              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <Activity className="h-5 w-5" />
                    Health & Performance
                  </CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                  <HealthIndicator 
                    status={client.health_status}
                    lastCheckedAt={client.last_health_check_at}
                  />
                  
                  {client.health_check_failures > 0 && (
                    <div className="flex items-center gap-2 text-sm text-red-600">
                      <AlertTriangle className="h-4 w-4" />
                      {client.health_check_failures} consecutive failures
                    </div>
                  )}

                  <Separator />

                  <div className="grid grid-cols-2 gap-4 text-sm">
                    <div>
                      <div className="font-medium">Total Tokens</div>
                      <div className="text-2xl font-bold">{stats.total_tokens}</div>
                    </div>
                    <div>
                      <div className="font-medium">Active Tokens</div>
                      <div className="text-2xl font-bold text-green-600">{stats.active_tokens}</div>
                    </div>
                    <div>
                      <div className="font-medium">Success Rate</div>
                      <div className="text-2xl font-bold">{stats.success_rate.toFixed(1)}%</div>
                    </div>
                    <div>
                      <div className="font-medium">Avg Response</div>
                      <div className="text-2xl font-bold">{stats.avg_response_time}ms</div>
                    </div>
                  </div>

                  {stats.last_activity && (
                    <div className="text-xs text-muted-foreground">
                      Last activity: {new Date(stats.last_activity).toLocaleString()}
                    </div>
                  )}
                </CardContent>
              </Card>
            </div>

            {/* Recent Events */}
            <RecentEvents events={events} maxEvents={10} />
          </TabsContent>

          <TabsContent value="configuration" className="space-y-6">
            <div className="grid gap-6 md:grid-cols-2">
              <Card>
                <CardHeader>
                  <CardTitle>OAuth Configuration</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div>
                    <label className="text-sm font-medium">Redirect URIs</label>
                    <div className="space-y-1 mt-1">
                      {client.redirect_uris.map((uri, index) => (
                        <code key={index} className="block px-2 py-1 bg-muted rounded text-sm font-mono">
                          {uri}
                        </code>
                      ))}
                    </div>
                  </div>

                  <div>
                    <label className="text-sm font-medium">Grant Types</label>
                    <div className="flex flex-wrap gap-1 mt-1">
                      {client.grants.map((grant) => (
                        <Badge key={grant} variant="outline" className="text-xs">
                          {grant.replace('_', ' ')}
                        </Badge>
                      ))}
                    </div>
                  </div>

                  <div>
                    <label className="text-sm font-medium">Scopes</label>
                    <div className="flex flex-wrap gap-1 mt-1">
                      {client.scopes.map((scope) => (
                        <Badge key={scope} variant="secondary" className="text-xs">
                          {scope}
                        </Badge>
                      ))}
                    </div>
                  </div>
                </CardContent>
              </Card>

              <Card>
                <CardHeader>
                  <CardTitle>Security Settings</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="grid grid-cols-2 gap-4 text-sm">
                    <div>
                      <div className="font-medium">Max Tokens</div>
                      <div className="text-lg font-semibold">{client.max_concurrent_tokens}</div>
                    </div>
                    <div>
                      <div className="font-medium">Rate Limit</div>
                      <div className="text-lg font-semibold">{client.rate_limit_per_minute}/min</div>
                    </div>
                  </div>

                  {client.tags && (
                    <div>
                      <label className="text-sm font-medium">Tags</label>
                      <div className="flex flex-wrap gap-1 mt-1">
                        {client.tags.split(',').map((tag, index) => (
                          <Badge key={index} variant="outline" className="text-xs">
                            {tag.trim()}
                          </Badge>
                        ))}
                      </div>
                    </div>
                  )}

                  <Separator />

                  <div className="flex justify-between items-center">
                    <span className="text-sm font-medium">Health Monitoring</span>
                    <Badge variant={client.health_check_enabled ? 'default' : 'secondary'}>
                      {client.health_check_enabled ? 'Enabled' : 'Disabled'}
                    </Badge>
                  </div>

                  {client.health_check_enabled && client.health_check_url && (
                    <div>
                      <label className="text-sm font-medium">Health Check URL</label>
                      <code className="block px-2 py-1 bg-muted rounded text-sm font-mono mt-1">
                        {client.health_check_url}
                      </code>
                      <div className="text-xs text-muted-foreground mt-1">
                        Interval: {client.health_check_interval} seconds
                      </div>
                    </div>
                  )}
                </CardContent>
              </Card>
            </div>
          </TabsContent>

          <TabsContent value="monitoring" className="space-y-6">
            <Card>
              <CardHeader>
                <CardTitle>Usage Analytics</CardTitle>
                <CardDescription>
                  Historical usage data and performance metrics
                </CardDescription>
              </CardHeader>
              <CardContent>
                {usage.length > 0 ? (
                  <div className="space-y-4">
                    {usage.slice(0, 7).map((dayUsage) => (
                      <div key={dayUsage.id} className="border-b pb-4">
                        <div className="flex justify-between items-center mb-2">
                          <h4 className="font-medium">{dayUsage.date}</h4>
                          <span className="text-sm text-muted-foreground">
                            {dayUsage.api_calls} API calls
                          </span>
                        </div>
                        <div className="grid grid-cols-4 gap-4 text-sm">
                          <div>
                            <span className="text-muted-foreground">Auth Requests</span>
                            <div className="font-semibold">{dayUsage.authorization_requests}</div>
                          </div>
                          <div>
                            <span className="text-muted-foreground">Token Requests</span>
                            <div className="font-semibold">{dayUsage.token_requests}</div>
                          </div>
                          <div>
                            <span className="text-muted-foreground">Unique Users</span>
                            <div className="font-semibold">{dayUsage.unique_users}</div>
                          </div>
                          <div>
                            <span className="text-muted-foreground">Errors</span>
                            <div className="font-semibold text-red-600">{dayUsage.error_count}</div>
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                ) : (
                  <p className="text-muted-foreground">No usage data available</p>
                )}
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="events" className="space-y-4">
            <Card>
              <CardHeader>
                <CardTitle>Event History</CardTitle>
                <CardDescription>
                  Complete log of client events and activities
                </CardDescription>
              </CardHeader>
              <CardContent>
                {events.length > 0 ? (
                  <div className="space-y-3">
                    {events.map((event) => (
                      <div key={event.id} className="flex items-start justify-between p-3 border rounded">
                        <div className="flex-1">
                          <div className="flex items-center gap-2 mb-1">
                            <EventSeverityBadge severity={event.severity} />
                            <span className="font-medium">{event.event_name}</span>
                            <span className="text-xs text-muted-foreground">
                              {new Date(event.occurred_at).toLocaleString()}
                            </span>
                          </div>
                          {event.event_description && (
                            <p className="text-sm text-muted-foreground mb-2">
                              {event.event_description}
                            </p>
                          )}
                          {event.ip_address && (
                            <span className="text-xs text-muted-foreground">
                              From {event.ip_address}
                            </span>
                          )}
                        </div>
                        {!event.is_resolved && event.severity === 'critical' && (
                          <Button variant="outline" size="sm">
                            Resolve
                          </Button>
                        )}
                      </div>
                    ))}
                  </div>
                ) : (
                  <p className="text-muted-foreground">No events recorded</p>
                )}
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="tokens">
            <Card>
              <CardHeader>
                <CardTitle>Token Management</CardTitle>
                <CardDescription>
                  Manage active tokens for this client
                </CardDescription>
              </CardHeader>
              <CardContent>
                <div className="flex justify-between items-center mb-4">
                  <div>
                    <div className="text-2xl font-bold">{stats.active_tokens}</div>
                    <div className="text-sm text-muted-foreground">Active tokens</div>
                  </div>
                  <AlertDialog>
                    <AlertDialogTrigger asChild>
                      <Button variant="outline" className="text-red-600">
                        <XCircle className="h-4 w-4 mr-2" />
                        Revoke All Tokens
                      </Button>
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                      <AlertDialogHeader>
                        <AlertDialogTitle>Revoke All Tokens</AlertDialogTitle>
                        <AlertDialogDescription>
                          This will revoke all active tokens for this client. Users will need to re-authenticate.
                        </AlertDialogDescription>
                      </AlertDialogHeader>
                      <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                          onClick={() => router.post(adminRoutes.oauthClients.revokeTokens(client.id))}
                          className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        >
                          Revoke All
                        </AlertDialogAction>
                      </AlertDialogFooter>
                    </AlertDialogContent>
                  </AlertDialog>
                </div>

                <Button asChild variant="outline" className="w-full">
                  <Link href={`${adminRoutes.tokens.index()}?client_id=${client.id}`}>
                    View All Tokens
                  </Link>
                </Button>
              </CardContent>
            </Card>
          </TabsContent>
        </Tabs>

        {/* Danger Zone */}
        <Card className="border-red-200">
          <CardHeader>
            <CardTitle className="text-red-600">Danger Zone</CardTitle>
            <CardDescription>
              Irreversible and destructive actions
            </CardDescription>
          </CardHeader>
          <CardContent>
            <AlertDialog>
              <AlertDialogTrigger asChild>
                <Button variant="destructive">
                  <Trash2 className="h-4 w-4 mr-2" />
                  Delete Client
                </Button>
              </AlertDialogTrigger>
              <AlertDialogContent>
                <AlertDialogHeader>
                  <AlertDialogTitle>Delete OAuth Client</AlertDialogTitle>
                  <AlertDialogDescription>
                    Are you sure you want to delete <strong>{client.name}</strong>? 
                    This will revoke all associated tokens and cannot be undone.
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
          </CardContent>
        </Card>
      </div>
    </AppLayout>
  );
}