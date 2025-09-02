import { StatsCard } from '@/components/ui/stats-card';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { adminRoutes } from '@/lib/admin-routes';
import { type BreadcrumbItem, type DashboardStats, type SecurityEvent, type AuditLog } from '@/types';
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
}

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Admin Dashboard',
    href: adminRoutes.dashboard(),
  },
];

export default function AdminDashboard({ stats, recentEvents, recentAuditLogs }: AdminDashboardProps) {
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
            title="Active Tokens"
            value={stats.tokens.active.toLocaleString()}
            description={`${stats.tokens.issued_today} issued today`}
            icon={Key}
          />
          
          <StatsCard
            title="OAuth Clients"
            value={stats.oauth_clients.total}
            description={`${stats.oauth_clients.active} active`}
            icon={Shield}
          />
          
          <StatsCard
            title="Security Events"
            value={stats.security.unresolved_events}
            description={`${stats.security.events_today} today`}
            icon={AlertTriangle}
            className={stats.security.unresolved_events > 0 ? "border-destructive" : ""}
          />
        </div>

        {/* Main Content Grid */}
        <div className="grid gap-6 lg:grid-cols-2">
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