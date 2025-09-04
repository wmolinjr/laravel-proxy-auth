import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import { CheckCircle, XCircle, Clock, AlertTriangle } from 'lucide-react';

interface HealthStatusProps {
  status: 'unknown' | 'healthy' | 'unhealthy' | 'error';
  size?: 'sm' | 'md' | 'lg';
  showIcon?: boolean;
  showText?: boolean;
  className?: string;
}

const statusConfig = {
  unknown: {
    label: 'Unknown',
    variant: 'secondary' as const,
    icon: Clock,
    color: 'text-gray-500',
    bgColor: 'bg-gray-100',
  },
  healthy: {
    label: 'Healthy',
    variant: 'default' as const,
    icon: CheckCircle,
    color: 'text-green-600',
    bgColor: 'bg-green-100',
  },
  unhealthy: {
    label: 'Unhealthy',
    variant: 'destructive' as const,
    icon: AlertTriangle,
    color: 'text-yellow-600',
    bgColor: 'bg-yellow-100',
  },
  error: {
    label: 'Error',
    variant: 'destructive' as const,
    icon: XCircle,
    color: 'text-red-600',
    bgColor: 'bg-red-100',
  },
};

export function HealthStatus({
  status,
  size = 'md',
  showIcon = true,
  showText = true,
  className,
}: HealthStatusProps) {
  const config = statusConfig[status];
  const Icon = config.icon;

  const sizeClasses = {
    sm: 'h-3 w-3',
    md: 'h-4 w-4',
    lg: 'h-5 w-5',
  };

  if (!showText) {
    return (
      <div
        className={cn(
          'inline-flex items-center justify-center rounded-full',
          config.bgColor,
          sizeClasses[size],
          className
        )}
        title={config.label}
      >
        {showIcon && (
          <Icon className={cn('h-2 w-2', config.color)} />
        )}
      </div>
    );
  }

  return (
    <Badge variant={config.variant} className={cn('inline-flex items-center gap-1', className)}>
      {showIcon && <Icon className={sizeClasses[size]} />}
      {showText && config.label}
    </Badge>
  );
}

export function HealthIndicator({
  status,
  lastCheckedAt,
  className,
}: {
  status: 'unknown' | 'healthy' | 'unhealthy' | 'error';
  lastCheckedAt?: string;
  className?: string;
}) {
  const config = statusConfig[status];

  return (
    <div className={cn('flex items-center gap-2', className)}>
      <div
        className={cn(
          'h-2 w-2 rounded-full animate-pulse',
          status === 'healthy' && 'bg-green-500',
          status === 'unhealthy' && 'bg-yellow-500',
          status === 'error' && 'bg-red-500',
          status === 'unknown' && 'bg-gray-400'
        )}
      />
      <div className="flex flex-col">
        <span className={cn('text-sm font-medium', config.color)}>
          {config.label}
        </span>
        {lastCheckedAt && (
          <span className="text-xs text-muted-foreground">
            Last check: {new Date(lastCheckedAt).toLocaleTimeString()}
          </span>
        )}
      </div>
    </div>
  );
}