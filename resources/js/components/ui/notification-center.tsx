import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import { Bell, Check, AlertTriangle, X } from 'lucide-react';
import { useState } from 'react';
import { router } from '@inertiajs/react';

interface Notification {
  id: number;
  type: 'critical' | 'alert' | 'warning' | 'info';
  title: string;
  message: string;
  oauth_client?: {
    id: string;
    name: string;
  };
  acknowledged_at?: string;
  created_at: string;
}

interface NotificationCenterProps {
  notifications: Notification[];
  unreadCount: number;
  className?: string;
}

export function NotificationCenter({ notifications, unreadCount, className }: NotificationCenterProps) {
  const [isOpen, setIsOpen] = useState(false);

  const handleAcknowledge = (id: number, event: React.MouseEvent) => {
    event.stopPropagation();
    router.post(`/api/oauth-notifications/${id}/acknowledge`);
  };

  const handleMarkAllRead = () => {
    router.post('/api/oauth-notifications/acknowledge-all');
  };

  const getTypeIcon = (type: string) => {
    switch (type) {
      case 'critical':
        return <AlertTriangle className="h-4 w-4 text-red-500" />;
      case 'alert':
        return <AlertTriangle className="h-4 w-4 text-orange-500" />;
      case 'warning':
        return <AlertTriangle className="h-4 w-4 text-yellow-500" />;
      default:
        return <Bell className="h-4 w-4 text-blue-500" />;
    }
  };

  const getTypeColor = (type: string) => {
    switch (type) {
      case 'critical':
        return 'border-red-200 bg-red-50';
      case 'alert':
        return 'border-orange-200 bg-orange-50';
      case 'warning':
        return 'border-yellow-200 bg-yellow-50';
      default:
        return 'border-blue-200 bg-blue-50';
    }
  };

  return (
    <DropdownMenu open={isOpen} onOpenChange={setIsOpen}>
      <DropdownMenuTrigger asChild>
        <Button variant="outline" size="sm" className={cn('relative', className)}>
          <Bell className="h-4 w-4" />
          {unreadCount > 0 && (
            <Badge 
              variant="destructive" 
              className="absolute -top-2 -right-2 h-5 w-5 flex items-center justify-center text-xs p-0 min-w-[20px]"
            >
              {unreadCount > 99 ? '99+' : unreadCount}
            </Badge>
          )}
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-80 max-h-96 overflow-y-auto">
        <DropdownMenuLabel className="flex items-center justify-between">
          <span>Notifications</span>
          {unreadCount > 0 && (
            <Button variant="ghost" size="sm" onClick={handleMarkAllRead}>
              <Check className="h-3 w-3 mr-1" />
              Mark all read
            </Button>
          )}
        </DropdownMenuLabel>
        <DropdownMenuSeparator />
        
        {notifications.length === 0 ? (
          <div className="p-4 text-center text-sm text-muted-foreground">
            No notifications
          </div>
        ) : (
          <div className="space-y-1">
            {notifications.map((notification) => (
              <div
                key={notification.id}
                className={cn(
                  'p-3 border rounded cursor-pointer hover:bg-muted/50 transition-colors',
                  getTypeColor(notification.type),
                  notification.acknowledged_at && 'opacity-60'
                )}
                onClick={() => router.visit(`/oauth-clients/${notification.oauth_client?.id || ''}`)}
              >
                <div className="flex items-start justify-between gap-2">
                  <div className="flex items-start gap-2 flex-1 min-w-0">
                    {getTypeIcon(notification.type)}
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center gap-2">
                        <p className="font-medium text-sm truncate">
                          {notification.title}
                        </p>
                        <Badge variant="outline" className="text-xs capitalize">
                          {notification.type}
                        </Badge>
                      </div>
                      <p className="text-xs text-muted-foreground mt-1 line-clamp-2">
                        {notification.message}
                      </p>
                      <div className="flex items-center justify-between mt-2">
                        {notification.oauth_client && (
                          <span className="text-xs text-muted-foreground">
                            {notification.oauth_client.name}
                          </span>
                        )}
                        <span className="text-xs text-muted-foreground">
                          {new Date(notification.created_at).toLocaleString()}
                        </span>
                      </div>
                    </div>
                  </div>
                  {!notification.acknowledged_at && (
                    <Button
                      variant="ghost"
                      size="sm"
                      className="h-6 w-6 p-0"
                      onClick={(e) => handleAcknowledge(notification.id, e)}
                    >
                      <X className="h-3 w-3" />
                    </Button>
                  )}
                </div>
              </div>
            ))}
          </div>
        )}
        
        <DropdownMenuSeparator />
        <DropdownMenuItem onClick={() => router.visit('/admin/notifications')}>
          View all notifications
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}

interface NotificationBannerProps {
  notification: Notification;
  onDismiss: () => void;
}

export function NotificationBanner({ notification, onDismiss }: NotificationBannerProps) {
  return (
    <Card className={cn('mb-4', getTypeColor(notification.type))}>
      <CardContent className="pt-4">
        <div className="flex items-start justify-between">
          <div className="flex items-start gap-3">
            {getTypeIcon(notification.type)}
            <div className="flex-1">
              <div className="flex items-center gap-2">
                <h4 className="font-semibold">{notification.title}</h4>
                <Badge variant="outline" className="text-xs capitalize">
                  {notification.type}
                </Badge>
              </div>
              <p className="text-sm mt-1">{notification.message}</p>
              <div className="flex items-center gap-4 mt-2 text-xs text-muted-foreground">
                {notification.oauth_client && (
                  <span>Client: {notification.oauth_client.name}</span>
                )}
                <span>{new Date(notification.created_at).toLocaleString()}</span>
              </div>
            </div>
          </div>
          <Button variant="ghost" size="sm" onClick={onDismiss}>
            <X className="h-4 w-4" />
          </Button>
        </div>
      </CardContent>
    </Card>
  );
}

// Helper function moved outside component to avoid recreation
function getTypeColor(type: string) {
  switch (type) {
    case 'critical':
      return 'border-red-200 bg-red-50';
    case 'alert':
      return 'border-orange-200 bg-orange-50';
    case 'warning':
      return 'border-yellow-200 bg-yellow-50';
    default:
      return 'border-blue-200 bg-blue-50';
  }
}

// Helper function for type icons
function getTypeIcon(type: string) {
  switch (type) {
    case 'critical':
      return <AlertTriangle className="h-4 w-4 text-red-500" />;
    case 'alert':
      return <AlertTriangle className="h-4 w-4 text-orange-500" />;
    case 'warning':
      return <AlertTriangle className="h-4 w-4 text-yellow-500" />;
    default:
      return <Bell className="h-4 w-4 text-blue-500" />;
  }
}