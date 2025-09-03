import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { EventSeverityBadge } from '@/components/ui/monitoring-metrics';
import AppLayout from '@/layouts/app-layout';
import { adminRoutes } from '@/lib/admin-routes';
import { type BreadcrumbItem, type OAuthClient, type OAuthClientEvent, type PaginatedResponse } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Calendar, Filter, Search, AlertTriangle, Info, AlertCircle, XCircle } from 'lucide-react';

interface OAuthClientEventsProps {
    client: OAuthClient;
    events: PaginatedResponse<OAuthClientEvent>;
    filters: {
        severity?: string;
        event_type?: string;
        search?: string;
        resolved?: string;
    };
    summary: {
        total_events: number;
        critical_events: number;
        unresolved_events: number;
        events_today: number;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: adminRoutes.dashboard() },
    { title: 'OAuth Clients', href: adminRoutes.oauthClients.index() },
];

const severityIcons = {
    info: Info,
    warning: AlertTriangle,
    error: AlertCircle,
    critical: XCircle,
};

const severityColors = {
    info: 'text-blue-600 dark:text-blue-400',
    warning: 'text-yellow-600 dark:text-yellow-400',
    error: 'text-red-600 dark:text-red-400',
    critical: 'text-red-800 dark:text-red-300',
};

export default function OAuthClientEvents({ client, events, filters, summary }: OAuthClientEventsProps) {
    const currentBreadcrumbs = [
        ...breadcrumbs,
        { title: client.name, href: adminRoutes.oauthClients.show(client.id) },
        { title: 'Events', href: adminRoutes.oauthClients.events(client.id) },
    ];

    return (
        <AppLayout breadcrumbs={currentBreadcrumbs}>
            <Head title={`Events - ${client.name}`} />
            
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Event History</h1>
                        <p className="text-lg text-muted-foreground">{client.name}</p>
                    </div>
                    <Button asChild>
                        <Link href={adminRoutes.oauthClients.show(client.id)}>
                            Back to Client
                        </Link>
                    </Button>
                </div>

                {/* Summary Cards */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Events</CardTitle>
                            <Calendar className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {summary.total_events.toLocaleString()}
                            </div>
                        </CardContent>
                    </Card>
                    
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Critical Events</CardTitle>
                            <XCircle className="h-4 w-4 text-red-600 dark:text-red-400" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-red-600 dark:text-red-400">
                                {summary.critical_events}
                            </div>
                        </CardContent>
                    </Card>
                    
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Unresolved</CardTitle>
                            <AlertTriangle className="h-4 w-4 text-yellow-600 dark:text-yellow-400" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-yellow-600 dark:text-yellow-400">
                                {summary.unresolved_events}
                            </div>
                        </CardContent>
                    </Card>
                    
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Today</CardTitle>
                            <Info className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-blue-600 dark:text-blue-400">
                                {summary.events_today}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters & Events */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Search className="h-5 w-5" />
                            Event Log
                        </CardTitle>
                        <CardDescription>
                            Complete history of client events and activities
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Tabs defaultValue="all" className="space-y-4">
                            <TabsList>
                                <TabsTrigger value="all">All Events</TabsTrigger>
                                <TabsTrigger value="critical">Critical</TabsTrigger>
                                <TabsTrigger value="error">Errors</TabsTrigger>
                                <TabsTrigger value="warning">Warnings</TabsTrigger>
                                <TabsTrigger value="unresolved">Unresolved</TabsTrigger>
                            </TabsList>

                            <TabsContent value="all" className="space-y-4">
                                {events.data.length > 0 ? (
                                    <div className="space-y-3">
                                        {events.data.map((event) => {
                                            const SeverityIcon = severityIcons[event.severity] || Info;
                                            return (
                                                <div key={event.id} className="flex items-start justify-between rounded-lg border p-4">
                                                    <div className="flex-1 space-y-2">
                                                        <div className="flex items-center gap-3">
                                                            <SeverityIcon className={`h-4 w-4 ${severityColors[event.severity]}`} />
                                                            <EventSeverityBadge severity={event.severity} />
                                                            <span className="font-medium">{event.event_name}</span>
                                                            <span className="text-sm text-muted-foreground">
                                                                {new Date(event.occurred_at).toLocaleString()}
                                                            </span>
                                                        </div>
                                                        
                                                        {event.event_description && (
                                                            <p className="text-sm text-muted-foreground pl-7">
                                                                {event.event_description}
                                                            </p>
                                                        )}
                                                        
                                                        <div className="flex items-center gap-4 text-xs text-muted-foreground pl-7">
                                                            <span className="capitalize">Type: {event.event_type}</span>
                                                            {event.ip_address && (
                                                                <span>IP: {event.ip_address}</span>
                                                            )}
                                                            {event.user && (
                                                                <span>User: {event.user.name}</span>
                                                            )}
                                                        </div>
                                                        
                                                        {event.data && Object.keys(event.data).length > 0 && (
                                                            <details className="pl-7">
                                                                <summary className="cursor-pointer text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                                                    View Details
                                                                </summary>
                                                                <pre className="mt-2 text-xs bg-muted p-2 rounded overflow-x-auto">
                                                                    {JSON.stringify(event.data, null, 2)}
                                                                </pre>
                                                            </details>
                                                        )}
                                                    </div>
                                                    
                                                    <div className="flex items-center gap-2">
                                                        {event.is_resolved ? (
                                                            <Badge variant="secondary" className="text-green-600 dark:text-green-400">
                                                                Resolved
                                                            </Badge>
                                                        ) : event.severity === 'critical' ? (
                                                            <Button variant="outline" size="sm">
                                                                Resolve
                                                            </Button>
                                                        ) : null}
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                ) : (
                                    <div className="text-center py-12">
                                        <Info className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
                                        <h3 className="text-lg font-semibold">No events found</h3>
                                        <p className="text-muted-foreground">
                                            No events have been recorded for this client.
                                        </p>
                                    </div>
                                )}
                            </TabsContent>
                            
                            {/* Other tab contents would filter the events by severity/status */}
                            <TabsContent value="critical">
                                <div className="text-center py-8 text-muted-foreground">
                                    Critical events would be filtered and displayed here
                                </div>
                            </TabsContent>
                            
                            <TabsContent value="error">
                                <div className="text-center py-8 text-muted-foreground">
                                    Error events would be filtered and displayed here
                                </div>
                            </TabsContent>
                            
                            <TabsContent value="warning">
                                <div className="text-center py-8 text-muted-foreground">
                                    Warning events would be filtered and displayed here
                                </div>
                            </TabsContent>
                            
                            <TabsContent value="unresolved">
                                <div className="text-center py-8 text-muted-foreground">
                                    Unresolved events would be filtered and displayed here
                                </div>
                            </TabsContent>
                        </Tabs>

                        {/* Pagination */}
                        {events.last_page > 1 && (
                            <div className="flex items-center justify-between mt-6">
                                <p className="text-sm text-muted-foreground">
                                    Showing {events.from} to {events.to} of {events.total} events
                                </p>
                                <div className="flex items-center gap-2">
                                    {events.links.map((link, index) => (
                                        <Button
                                            key={index}
                                            variant={link.active ? "default" : "outline"}
                                            size="sm"
                                            disabled={!link.url}
                                            asChild={!!link.url}
                                        >
                                            {link.url ? (
                                                <Link href={link.url}>{link.label}</Link>
                                            ) : (
                                                <span>{link.label}</span>
                                            )}
                                        </Button>
                                    ))}
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}