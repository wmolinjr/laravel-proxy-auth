import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { StatsCard } from '@/components/ui/stats-card';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { adminRoutes } from '@/lib/admin-routes';
import { type BreadcrumbItem, type AnalyticsData } from '@/types';
import { Head } from '@inertiajs/react';
import { 
  Users, 
  Shield, 
  Key, 
  AlertTriangle, 
  FileText,
  TrendingUp,
  Activity,
  Database,
  Clock
} from 'lucide-react';

interface AdminAnalyticsProps {
  analytics: AnalyticsData;
}

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Admin', href: adminRoutes.dashboard() },
  { title: 'Analytics', href: adminRoutes.analytics() },
];

export default function AdminAnalytics({ analytics }: AdminAnalyticsProps) {
  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Analytics" />
      
      <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold tracking-tight">Analytics</h1>
            <p className="text-muted-foreground">
              Detailed insights and metrics for your authentication server
            </p>
          </div>
        </div>

        {/* User Analytics */}
        <div className="space-y-4">
          <h2 className="text-xl font-semibold">User Metrics</h2>
          <div className="grid gap-4 md:grid-cols-4">
            <StatsCard
              title="Total Users"
              value={analytics.users.total.toLocaleString()}
              icon={Users}
            />
            <StatsCard
              title="Active Users"
              value={analytics.users.active.toLocaleString()}
              icon={Activity}
            />
            <StatsCard
              title="Admin Users"
              value={analytics.users.admins.toLocaleString()}
              icon={Shield}
            />
            <StatsCard
              title="New This Month"
              value={analytics.users.recent_registrations.toLocaleString()}
              icon={TrendingUp}
            />
          </div>

          {/* User Growth Chart */}
          <Card>
            <CardHeader>
              <CardTitle>User Registration Growth (Last 30 Days)</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="h-64 flex items-center justify-center border rounded-lg bg-muted/50">
                <div className="text-center text-muted-foreground">
                  <TrendingUp className="h-8 w-8 mx-auto mb-2" />
                  <p>Chart visualization would be implemented here</p>
                  <p className="text-sm">Data points: {analytics.users.growth_chart.length}</p>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

        {/* OAuth Analytics */}
        <div className="space-y-4">
          <h2 className="text-xl font-semibold">OAuth Metrics</h2>
          <div className="grid gap-4 md:grid-cols-3">
            <StatsCard
              title="OAuth Clients"
              value={analytics.oauth.clients.toLocaleString()}
              icon={Shield}
            />
            <StatsCard
              title="Active Tokens"
              value={analytics.oauth.active_tokens.toLocaleString()}
              icon={Key}
            />
            <StatsCard
              title="Token Usage Points"
              value={analytics.oauth.token_usage_chart.length.toLocaleString()}
              icon={Activity}
            />
          </div>

          <div className="grid gap-6 lg:grid-cols-2">
            {/* Token Usage Chart */}
            <Card>
              <CardHeader>
                <CardTitle>Token Usage (Last 30 Days)</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="h-48 flex items-center justify-center border rounded-lg bg-muted/50">
                  <div className="text-center text-muted-foreground">
                    <Key className="h-6 w-6 mx-auto mb-2" />
                    <p className="text-sm">Token usage chart</p>
                  </div>
                </div>
              </CardContent>
            </Card>

            {/* Client Usage */}
            <Card>
              <CardHeader>
                <CardTitle>Client Token Usage</CardTitle>
              </CardHeader>
              <CardContent className="space-y-3">
                {analytics.oauth.client_usage.length > 0 ? (
                  analytics.oauth.client_usage.map((client, index) => (
                    <div key={index} className="flex items-center justify-between p-3 border rounded-lg">
                      <div className="font-medium">{client.name}</div>
                      <Badge variant="secondary">
                        {client.active_tokens} tokens
                      </Badge>
                    </div>
                  ))
                ) : (
                  <p className="text-center text-muted-foreground py-8">
                    No client usage data available
                  </p>
                )}
              </CardContent>
            </Card>
          </div>
        </div>

        {/* Security Analytics */}
        <div className="space-y-4">
          <h2 className="text-xl font-semibold">Security Metrics</h2>
          <div className="grid gap-4 md:grid-cols-4">
            <StatsCard
              title="Events (30 days)"
              value={analytics.security.events_last_30_days.toLocaleString()}
              icon={AlertTriangle}
            />
            <StatsCard
              title="Unresolved Events"
              value={analytics.security.unresolved_events.toLocaleString()}
              icon={AlertTriangle}
              className={analytics.security.unresolved_events > 0 ? "border-destructive" : ""}
            />
            <StatsCard
              title="High Severity"
              value={analytics.security.high_severity_events.toLocaleString()}
              icon={AlertTriangle}
              className={analytics.security.high_severity_events > 0 ? "border-orange-500" : ""}
            />
            <StatsCard
              title="Chart Points"
              value={analytics.security.security_chart.length.toLocaleString()}
              icon={Activity}
            />
          </div>

          <div className="grid gap-6 lg:grid-cols-2">
            {/* Security Events Chart */}
            <Card>
              <CardHeader>
                <CardTitle>Security Events (Last 30 Days)</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="h-48 flex items-center justify-center border rounded-lg bg-muted/50">
                  <div className="text-center text-muted-foreground">
                    <AlertTriangle className="h-6 w-6 mx-auto mb-2" />
                    <p className="text-sm">Security events chart</p>
                  </div>
                </div>
              </CardContent>
            </Card>

            {/* Events by Type */}
            <Card>
              <CardHeader>
                <CardTitle>Events by Type</CardTitle>
              </CardHeader>
              <CardContent className="space-y-3">
                {Object.entries(analytics.security.events_by_type).length > 0 ? (
                  Object.entries(analytics.security.events_by_type).map(([type, count]) => (
                    <div key={type} className="flex items-center justify-between p-3 border rounded-lg">
                      <div className="font-medium">
                        {type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}
                      </div>
                      <Badge variant={count > 10 ? 'destructive' : count > 5 ? 'default' : 'secondary'}>
                        {count} events
                      </Badge>
                    </div>
                  ))
                ) : (
                  <p className="text-center text-muted-foreground py-8">
                    No security events data available
                  </p>
                )}
              </CardContent>
            </Card>
          </div>
        </div>

        {/* System Analytics */}
        <div className="space-y-4">
          <h2 className="text-xl font-semibold">System Metrics</h2>
          <div className="grid gap-4 md:grid-cols-3">
            <StatsCard
              title="Audit Logs (30 days)"
              value={analytics.system.audit_logs_last_30_days.toLocaleString()}
              icon={FileText}
            />
            <StatsCard
              title="Database Size"
              value={analytics.system.database_size}
              icon={Database}
            />
            <StatsCard
              title="System Uptime"
              value={analytics.system.uptime}
              icon={Clock}
            />
          </div>
        </div>

        {/* Additional Insights */}
        <Card>
          <CardHeader>
            <CardTitle>System Health Summary</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
              <div className="flex items-center gap-3 p-4 border rounded-lg">
                <div className={`w-3 h-3 rounded-full ${
                  analytics.security.unresolved_events === 0 ? 'bg-green-500' : 'bg-red-500'
                }`}></div>
                <div>
                  <div className="font-medium">Security Status</div>
                  <div className="text-sm text-muted-foreground">
                    {analytics.security.unresolved_events === 0 ? 'All Clear' : `${analytics.security.unresolved_events} Issues`}
                  </div>
                </div>
              </div>

              <div className="flex items-center gap-3 p-4 border rounded-lg">
                <div className="w-3 h-3 rounded-full bg-green-500"></div>
                <div>
                  <div className="font-medium">OAuth Health</div>
                  <div className="text-sm text-muted-foreground">
                    {analytics.oauth.clients} clients active
                  </div>
                </div>
              </div>

              <div className="flex items-center gap-3 p-4 border rounded-lg">
                <div className={`w-3 h-3 rounded-full ${
                  analytics.users.active > analytics.users.total * 0.8 ? 'bg-green-500' : 'bg-yellow-500'
                }`}></div>
                <div>
                  <div className="font-medium">User Activity</div>
                  <div className="text-sm text-muted-foreground">
                    {Math.round((analytics.users.active / analytics.users.total) * 100)}% active
                  </div>
                </div>
              </div>

              <div className="flex items-center gap-3 p-4 border rounded-lg">
                <div className="w-3 h-3 rounded-full bg-blue-500"></div>
                <div>
                  <div className="font-medium">System Load</div>
                  <div className="text-sm text-muted-foreground">
                    {analytics.system.uptime}
                  </div>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </AppLayout>
  );
}