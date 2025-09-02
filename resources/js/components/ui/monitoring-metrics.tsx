import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { TrendingUp, TrendingDown, Minus, Activity, Users, Zap, AlertTriangle } from 'lucide-react';
import { type OAuthClientUsage, type OAuthClientEvent } from '@/types';

interface MetricCardProps {
  title: string;
  value: string | number;
  description?: string;
  trend?: 'up' | 'down' | 'neutral';
  trendValue?: string;
  icon?: React.ComponentType<{ className?: string }>;
  className?: string;
}

export function MetricCard({
  title,
  value,
  description,
  trend,
  trendValue,
  icon: Icon,
  className,
}: MetricCardProps) {
  const TrendIcon = trend === 'up' ? TrendingUp : trend === 'down' ? TrendingDown : Minus;

  return (
    <Card className={cn('', className)}>
      <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
        <CardTitle className="text-sm font-medium">{title}</CardTitle>
        {Icon && <Icon className="h-4 w-4 text-muted-foreground" />}
      </CardHeader>
      <CardContent>
        <div className="text-2xl font-bold">{value}</div>
        {(description || trendValue) && (
          <div className="flex items-center space-x-2 text-xs text-muted-foreground">
            {trendValue && (
              <div className={cn(
                'flex items-center',
                trend === 'up' && 'text-green-600',
                trend === 'down' && 'text-red-600',
                trend === 'neutral' && 'text-gray-600'
              )}>
                <TrendIcon className="h-3 w-3 mr-1" />
                {trendValue}
              </div>
            )}
            {description && <span>{description}</span>}
          </div>
        )}
      </CardContent>
    </Card>
  );
}

interface UsageMetricsProps {
  usage?: OAuthClientUsage;
  className?: string;
}

export function UsageMetrics({ usage, className }: UsageMetricsProps) {
  if (!usage) {
    return (
      <div className={cn('grid gap-4 md:grid-cols-2 lg:grid-cols-4', className)}>
        {[...Array(4)].map((_, i) => (
          <Card key={i} className="animate-pulse">
            <CardHeader className="space-y-0 pb-2">
              <div className="h-4 bg-gray-200 rounded w-3/4"></div>
            </CardHeader>
            <CardContent>
              <div className="h-8 bg-gray-200 rounded w-1/2 mb-2"></div>
              <div className="h-3 bg-gray-200 rounded w-full"></div>
            </CardContent>
          </Card>
        ))}
      </div>
    );
  }

  const authSuccessRate = usage.authorization_requests > 0 
    ? (usage.successful_authorizations / usage.authorization_requests * 100).toFixed(1)
    : '0';

  const tokenSuccessRate = usage.token_requests > 0
    ? (usage.successful_tokens / usage.token_requests * 100).toFixed(1)
    : '0';

  return (
    <div className={cn('grid gap-4 md:grid-cols-2 lg:grid-cols-4', className)}>
      <MetricCard
        title="API Calls"
        value={usage.api_calls.toLocaleString()}
        description="Total requests"
        icon={Activity}
      />
      <MetricCard
        title="Active Users"
        value={usage.unique_users}
        description={`Peak: ${usage.peak_concurrent_users}`}
        icon={Users}
      />
      <MetricCard
        title="Token Requests"
        value={usage.token_requests}
        description={`${tokenSuccessRate}% success rate`}
        trend={parseFloat(tokenSuccessRate) >= 95 ? 'up' : parseFloat(tokenSuccessRate) >= 80 ? 'neutral' : 'down'}
        icon={Zap}
      />
      <MetricCard
        title="Errors"
        value={usage.error_count}
        description="Total errors"
        trend={usage.error_count === 0 ? 'up' : usage.error_count < 10 ? 'neutral' : 'down'}
        icon={AlertTriangle}
      />
    </div>
  );
}

interface EventSeverityBadgeProps {
  severity: 'info' | 'warning' | 'error' | 'critical';
  className?: string;
}

export function EventSeverityBadge({ severity, className }: EventSeverityBadgeProps) {
  const config = {
    info: { label: 'Info', variant: 'secondary' as const },
    warning: { label: 'Warning', variant: 'outline' as const },
    error: { label: 'Error', variant: 'destructive' as const },
    critical: { label: 'Critical', variant: 'destructive' as const },
  };

  const { label, variant } = config[severity];

  return (
    <Badge variant={variant} className={cn('text-xs', className)}>
      {label}
    </Badge>
  );
}

interface RecentEventsProps {
  events: OAuthClientEvent[];
  maxEvents?: number;
  className?: string;
}

export function RecentEvents({ events, maxEvents = 5, className }: RecentEventsProps) {
  const recentEvents = events.slice(0, maxEvents);

  if (recentEvents.length === 0) {
    return (
      <Card className={cn('', className)}>
        <CardHeader>
          <CardTitle className="text-sm">Recent Events</CardTitle>
        </CardHeader>
        <CardContent>
          <p className="text-sm text-muted-foreground">No recent events</p>
        </CardContent>
      </Card>
    );
  }

  return (
    <Card className={cn('', className)}>
      <CardHeader>
        <CardTitle className="text-sm">Recent Events</CardTitle>
        <CardDescription>
          Last {recentEvents.length} events
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-3">
        {recentEvents.map((event) => (
          <div key={event.id} className="flex items-start justify-between space-x-3">
            <div className="flex-1 min-w-0">
              <div className="flex items-center gap-2">
                <EventSeverityBadge severity={event.severity} />
                <span className="text-sm font-medium truncate">{event.event_name}</span>
              </div>
              {event.event_description && (
                <p className="text-xs text-muted-foreground mt-1 truncate">
                  {event.event_description}
                </p>
              )}
              <p className="text-xs text-muted-foreground mt-1">
                {new Date(event.occurred_at).toLocaleString()}
              </p>
            </div>
          </div>
        ))}
      </CardContent>
    </Card>
  );
}