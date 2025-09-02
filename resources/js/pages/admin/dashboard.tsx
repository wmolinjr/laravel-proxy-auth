import { StatsCard } from '@/components/ui/stats-card';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { HealthStatus } from '@/components/ui/health-status';
import { MetricCard, RecentEvents } from '@/components/ui/monitoring-metrics';
import { AnalyticsChart, Sparkline } from '@/components/ui/analytics-chart';
import AppLayout from '@/layouts/app-layout';
import { adminRoutes } from '@/lib/admin-routes';
import { type BreadcrumbItem, type DashboardStats, type SecurityEvent, type AuditLog, type OAuthClient, type OAuthClientEvent } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { 
  Users, 
  Shield, 
  Key, 
  AlertTriangle, 
  FileText, 
  BarChart3,
  Activity,
  TrendingUp,
  ExternalLink 
} from 'lucide-react';

interface AdminDashboardProps {
  stats: DashboardStats;
  recentEvents: SecurityEvent[];
  recentAuditLogs: AuditLog[];
  oauthClients: OAuthClient[];
  oauthEvents: OAuthClientEvent[];
  oauthStats: {
    healthy: number;
    unhealthy: number;
    maintenance: number;
    total_requests_today: number;
    success_rate: number;
    avg_response_time: number;
    usage_trend: Array<{ date: string; value: number }>;
  };
}

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Admin Dashboard',
    href: adminRoutes.dashboard(),
  },
];

export default function AdminDashboard({ stats, recentEvents, recentAuditLogs, oauthClients = [], oauthEvents = [], oauthStats }: AdminDashboardProps) {
  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Admin Dashboard" />
      
      <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold tracking-tight">Admin Dashboard</h1>
            <p className="text-muted-foreground">
              Monitor your OAuth authentication server and manage system resources
            </p>
          </div>
          <div className="flex items-center gap-2">
            <Button variant="outline" asChild>
              <Link href={adminRoutes.analytics()}>
                <BarChart3 className="h-4 w-4 mr-2" />
                View Analytics
              </Link>
            </Button>
            <Button variant="outline" asChild>
              <Link href={adminRoutes.settings.index()}>
                <Activity className="h-4 w-4 mr-2" />
                Settings
              </Link>
            </Button>
          </div>
        </div>

        {/* Stats Grid */}
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
          <StatsCard
            title="Total Users"
            value={stats.users.total.toLocaleString()}
            description={`${stats.users.new_today} new today`}
            icon={Users}
            trend={stats.users.growth_percentage ? {
              value: stats.users.growth_percentage,
              isPositive: stats.users.growth_percentage > 0
            } : undefined}
          />
          
          <StatsCard
            title="OAuth Requests"
            value={oauthStats?.total_requests_today?.toLocaleString() || '0'}
            description={`${oauthStats?.success_rate?.toFixed(1) || 0}% success rate`}
            icon={Key}
            trend={oauthStats?.success_rate >= 95 ? { value: oauthStats.success_rate, isPositive: true } : undefined}
          />
          
          <StatsCard
            title="OAuth Clients"
            value={stats.oauth_clients.total}
            description={`${oauthStats?.healthy || 0} healthy, ${oauthStats?.unhealthy || 0} unhealthy`}
            icon={Shield}
            className={oauthStats?.unhealthy > 0 ? "border-orange-500" : ""}
          />
          
          <StatsCard
            title="Response Time"
            value={`${oauthStats?.avg_response_time || 0}ms`}
            description="Average response time"
            icon={Activity}
            className={oauthStats?.avg_response_time > 1000 ? "border-yellow-500" : ""}
          />
        </div>

        {/* OAuth Health Overview */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Shield className="h-5 w-5" />
              OAuth System Health
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid gap-4 md:grid-cols-3 lg:grid-cols-5">
              <MetricCard
                title="Healthy Clients"
                value={oauthStats?.healthy || 0}
                icon={Activity}
                className="border-green-200"
              />
              <MetricCard
                title="Unhealthy"
                value={oauthStats?.unhealthy || 0}
                icon={AlertTriangle}
                className={oauthStats?.unhealthy > 0 ? "border-red-200" : ""}
              />
              <MetricCard
                title="Maintenance"
                value={oauthStats?.maintenance || 0}
                icon={Activity}
                className="border-yellow-200"
              />
              <div className="col-span-2">
                <AnalyticsChart
                  title="Usage Trend (7 days)"
                  data={oauthStats?.usage_trend || []}
                  type="area"
                  height={100}
                  showTrend={false}
                  className="h-auto"
                />
              </div>
            </div>
            
            {/* Client Health Status */}
            {oauthClients.length > 0 && (
              <div className="mt-4 space-y-2">
                <h4 className="font-medium text-sm">Client Status Overview</h4>
                <div className="grid gap-2 md:grid-cols-2 lg:grid-cols-3">
                  {oauthClients.slice(0, 6).map((client) => (
                    <div key={client.id} className="flex items-center justify-between p-2 border rounded">
                      <div className="flex items-center gap-2 min-w-0 flex-1">
                        <HealthStatus status={client.health_status} size="sm" showText={false} />
                        <span className="text-sm truncate">{client.name}</span>
                        <Badge variant="outline" className="text-xs capitalize shrink-0">
                          {client.environment}
                        </Badge>
                      </div>
                      {client.usage_stats && (
                        <Sparkline 
                          data={[client.usage_stats.api_calls]} 
                          className="ml-2" 
                        />
                      )}
                    </div>
                  ))}
                </div>
                {oauthClients.length > 6 && (
                  <Button variant="ghost" size="sm" asChild className="w-full mt-2">
                    <Link href={adminRoutes.oauthClients.index()}>
                      View all {oauthClients.length} OAuth clients
                    </Link>
                  </Button>
                )}
              </div>
            )}
          </CardContent>
        </Card>

        {/* Main Content Grid */}
        <div className="grid gap-6 lg:grid-cols-3">
          {/* Recent OAuth Events */}
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-lg font-semibold">OAuth Events</CardTitle>
              <Button variant="ghost" size="sm" asChild>
                <Link href={adminRoutes.oauthClients.index()}>
                  <ExternalLink className="h-4 w-4" />
                </Link>
              </Button>
            </CardHeader>
            <CardContent>
              <RecentEvents events={oauthEvents} maxEvents={3} className="border-0 shadow-none p-0" />
            </CardContent>
          </Card>

          {/* Recent Security Events */}
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-lg font-semibold">Recent Security Events</CardTitle>
              <Button variant="ghost" size="sm" asChild>
                <Link href={adminRoutes.securityEvents.index()}>
                  <ExternalLink className="h-4 w-4" />
                </Link>
              </Button>
            </CardHeader>
            <CardContent className="space-y-4">
              {recentEvents.length === 0 ? (
                <p className="text-sm text-muted-foreground py-4">No recent security events</p>
              ) : (
                recentEvents.slice(0, 5).map((event) => (
                  <div key={event.id} className="flex items-start justify-between space-x-4">
                    <div className="flex-1 min-w-0">
                      <p className="text-sm font-medium truncate">
                        {event.event_description || event.event_type}
                      </p>
                      <div className="flex items-center gap-2 mt-1">
                        <Badge 
                          variant={
                            event.severity === 'critical' || event.severity === 'high' 
                              ? 'destructive' 
                              : event.severity === 'medium' 
                              ? 'default' 
                              : 'secondary'
                          }
                          className="text-xs"
                        >
                          {event.severity}
                        </Badge>
                        {event.user && (
                          <span className="text-xs text-muted-foreground">
                            {event.user.name}
                          </span>
                        )}
                        <span className="text-xs text-muted-foreground">
                          {event.created_at}
                        </span>
                      </div>
                    </div>
                    {!event.is_resolved && (
                      <Badge variant="outline" className="shrink-0">
                        Unresolved
                      </Badge>
                    )}
                  </div>
                ))
              )}
              {recentEvents.length > 5 && (
                <Button variant="ghost" size="sm" asChild className="w-full">
                  <Link href={adminRoutes.securityEvents.index()}>
                    View all security events
                  </Link>
                </Button>
              )}
            </CardContent>
          </Card>

          {/* Recent Audit Logs */}
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-lg font-semibold">Recent Activity</CardTitle>
              <Button variant="ghost" size="sm" asChild>
                <Link href={adminRoutes.auditLogs.index()}>
                  <ExternalLink className="h-4 w-4" />
                </Link>
              </Button>
            </CardHeader>
            <CardContent className="space-y-4">
              {recentAuditLogs.length === 0 ? (
                <p className="text-sm text-muted-foreground py-4">No recent activity</p>
              ) : (
                recentAuditLogs.slice(0, 5).map((log) => (
                  <div key={log.id} className="flex items-start space-x-4">
                    <div className="flex-shrink-0">
                      <div className="w-2 h-2 bg-primary rounded-full mt-2"></div>
                    </div>
                    <div className="flex-1 min-w-0">
                      <p className="text-sm font-medium">
                        {log.event_type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}
                      </p>
                      {log.entity_type && (
                        <p className="text-xs text-muted-foreground">
                          {log.entity_type} {log.entity_id && `#${log.entity_id}`}
                        </p>
                      )}
                      <div className="flex items-center gap-2 mt-1">
                        {log.user && (
                          <span className="text-xs text-muted-foreground">
                            by {log.user.name}
                          </span>
                        )}
                        <span className="text-xs text-muted-foreground">
                          {log.created_at}
                        </span>
                      </div>
                    </div>
                  </div>
                ))
              )}
              {recentAuditLogs.length > 5 && (
                <Button variant="ghost" size="sm" asChild className="w-full">
                  <Link href={adminRoutes.auditLogs.index()}>
                    View all audit logs
                  </Link>
                </Button>
              )}
            </CardContent>
          </Card>
        </div>

        {/* Quick Actions */}
        <Card>
          <CardHeader>
            <CardTitle className="text-lg font-semibold">Quick Actions</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
              <Button variant="outline" className="justify-start" asChild>
                <Link href={adminRoutes.users.create()}>
                  <Users className="h-4 w-4 mr-2" />
                  Add User
                </Link>
              </Button>
              
              <Button variant="outline" className="justify-start" asChild>
                <Link href={adminRoutes.oauthClients.create()}>
                  <Shield className="h-4 w-4 mr-2" />
                  Create OAuth Client
                </Link>
              </Button>
              
              <Button variant="outline" className="justify-start" asChild>
                <Link href={adminRoutes.tokens.index()}>
                  <Key className="h-4 w-4 mr-2" />
                  Manage Tokens
                </Link>
              </Button>
              
              <Button variant="outline" className="justify-start" asChild>
                <Link href={adminRoutes.analytics()}>
                  <TrendingUp className="h-4 w-4 mr-2" />
                  View Analytics
                </Link>
              </Button>
            </div>
          </CardContent>
        </Card>

        {/* System Health */}
        <div className="grid gap-4 md:grid-cols-3">
          <StatsCard
            title="Active Users"
            value={stats.users.active}
            description="Currently online"
            icon={Activity}
          />
          
          <StatsCard
            title="Audit Events"
            value={stats.audit_logs.today}
            description="Today"
            icon={FileText}
          />
          
          <StatsCard
            title="High Priority Events"
            value={stats.security.high_severity}
            description="Last 7 days"
            icon={AlertTriangle}
            className={stats.security.high_severity > 0 ? "border-orange-500" : ""}
          />
        </div>
      </div>
    </AppLayout>
  );
}