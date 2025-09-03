import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { adminRoutes } from '@/lib/admin-routes';
import { type BreadcrumbItem, type OAuthClient, type OAuthClientUsage, type PaginatedResponse } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { BarChart3, TrendingUp, Users, Zap, Calendar, Activity, AlertCircle, CheckCircle } from 'lucide-react';

interface OAuthClientUsageProps {
    client: OAuthClient;
    usage: PaginatedResponse<OAuthClientUsage>;
    summary: {
        total_api_calls: number;
        total_unique_users: number;
        average_success_rate: number;
        peak_concurrent_users: number;
        total_data_transferred: number;
        current_period_growth: number;
    };
    charts: {
        daily_usage: Array<{
            date: string;
            api_calls: number;
            unique_users: number;
            success_rate: number;
        }>;
        hourly_distribution: Array<{
            hour: number;
            api_calls: number;
            peak_users: number;
        }>;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: adminRoutes.dashboard() },
    { title: 'OAuth Clients', href: adminRoutes.oauthClients.index() },
];

export default function OAuthClientUsage({ client, usage, summary, charts }: OAuthClientUsageProps) {
    const currentBreadcrumbs = [
        ...breadcrumbs,
        { title: client.name, href: adminRoutes.oauthClients.show(client.id) },
        { title: 'Usage', href: adminRoutes.oauthClients.usage(client.id) },
    ];

    const formatBytes = (bytes: number) => {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
    };

    return (
        <AppLayout breadcrumbs={currentBreadcrumbs}>
            <Head title={`Usage Analytics - ${client.name}`} />
            
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Usage Analytics</h1>
                        <p className="text-lg text-muted-foreground">{client.name}</p>
                    </div>
                    <Button asChild>
                        <Link href={adminRoutes.oauthClients.show(client.id)}>
                            Back to Client
                        </Link>
                    </Button>
                </div>

                {/* Summary Cards */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total API Calls</CardTitle>
                            <Zap className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {summary.total_api_calls.toLocaleString()}
                            </div>
                            {summary.current_period_growth > 0 && (
                                <p className="text-xs text-green-600 dark:text-green-400 flex items-center gap-1">
                                    <TrendingUp className="h-3 w-3" />
                                    +{summary.current_period_growth}% from last period
                                </p>
                            )}
                        </CardContent>
                    </Card>
                    
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Unique Users</CardTitle>
                            <Users className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {summary.total_unique_users.toLocaleString()}
                            </div>
                        </CardContent>
                    </Card>
                    
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Success Rate</CardTitle>
                            <CheckCircle className="h-4 w-4 text-green-600 dark:text-green-400" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-green-600 dark:text-green-400">
                                {summary.average_success_rate.toFixed(1)}%
                            </div>
                        </CardContent>
                    </Card>
                    
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Peak Users</CardTitle>
                            <Activity className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {summary.peak_concurrent_users}
                            </div>
                        </CardContent>
                    </Card>
                    
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Data Transfer</CardTitle>
                            <BarChart3 className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {formatBytes(summary.total_data_transferred)}
                            </div>
                        </CardContent>
                    </Card>
                    
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Status</CardTitle>
                            <Activity className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <Badge variant={client.is_active ? 'default' : 'secondary'} className="text-xs">
                                {client.is_active ? 'Active' : 'Inactive'}
                            </Badge>
                        </CardContent>
                    </Card>
                </div>

                {/* Charts & Analytics */}
                <Tabs defaultValue="overview" className="space-y-6">
                    <TabsList>
                        <TabsTrigger value="overview">Overview</TabsTrigger>
                        <TabsTrigger value="daily">Daily Trends</TabsTrigger>
                        <TabsTrigger value="hourly">Hourly Distribution</TabsTrigger>
                        <TabsTrigger value="detailed">Detailed Records</TabsTrigger>
                    </TabsList>

                    <TabsContent value="overview" className="space-y-6">
                        <div className="grid gap-6 md:grid-cols-2">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Recent Activity</CardTitle>
                                    <CardDescription>Last 7 days of API usage</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-4">
                                        {charts.daily_usage.slice(0, 7).map((day) => (
                                            <div key={day.date} className="flex items-center justify-between">
                                                <div className="flex flex-col">
                                                    <span className="text-sm font-medium">
                                                        {new Date(day.date).toLocaleDateString()}
                                                    </span>
                                                    <span className="text-xs text-muted-foreground">
                                                        {day.unique_users} unique users
                                                    </span>
                                                </div>
                                                <div className="flex items-center gap-4 text-sm">
                                                    <span>{day.api_calls.toLocaleString()} calls</span>
                                                    <Badge variant={day.success_rate >= 95 ? 'default' : 'secondary'} className="text-xs">
                                                        {day.success_rate.toFixed(1)}% success
                                                    </Badge>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Usage Distribution</CardTitle>
                                    <CardDescription>Peak usage by hour (24h format)</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-2">
                                        {charts.hourly_distribution.slice(0, 12).map((hour) => (
                                            <div key={hour.hour} className="flex items-center justify-between">
                                                <span className="text-sm">
                                                    {hour.hour.toString().padStart(2, '0')}:00
                                                </span>
                                                <div className="flex items-center gap-2">
                                                    <div className="w-24 bg-muted rounded-full h-2">
                                                        <div 
                                                            className="bg-blue-600 dark:bg-blue-400 h-2 rounded-full" 
                                                            style={{ 
                                                                width: `${Math.min((hour.api_calls / Math.max(...charts.hourly_distribution.map(h => h.api_calls))) * 100, 100)}%` 
                                                            }}
                                                        />
                                                    </div>
                                                    <span className="text-xs text-muted-foreground w-12 text-right">
                                                        {hour.api_calls}
                                                    </span>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </TabsContent>

                    <TabsContent value="detailed" className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Calendar className="h-5 w-5" />
                                    Daily Usage Records
                                </CardTitle>
                                <CardDescription>
                                    Detailed breakdown of daily usage statistics
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {usage.data.length > 0 ? (
                                    <div className="space-y-4">
                                        {usage.data.map((record) => (
                                            <div key={record.id} className="border rounded-lg p-4">
                                                <div className="flex items-center justify-between mb-3">
                                                    <div className="flex items-center gap-3">
                                                        <h4 className="font-semibold">
                                                            {new Date(record.date).toLocaleDateString('en-US', { 
                                                                weekday: 'long', 
                                                                year: 'numeric', 
                                                                month: 'long', 
                                                                day: 'numeric' 
                                                            })}
                                                        </h4>
                                                        <Badge variant="outline">
                                                            {record.api_calls.toLocaleString()} total calls
                                                        </Badge>
                                                    </div>
                                                    {record.last_activity_at && (
                                                        <span className="text-sm text-muted-foreground">
                                                            Last activity: {new Date(record.last_activity_at).toLocaleTimeString()}
                                                        </span>
                                                    )}
                                                </div>
                                                
                                                <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                                    <div>
                                                        <span className="text-muted-foreground block">Authorization Requests</span>
                                                        <div className="font-semibold">
                                                            {record.authorization_requests}
                                                            <span className="text-green-600 dark:text-green-400 ml-2">
                                                                ({record.successful_authorizations} success)
                                                            </span>
                                                        </div>
                                                    </div>
                                                    
                                                    <div>
                                                        <span className="text-muted-foreground block">Token Requests</span>
                                                        <div className="font-semibold">
                                                            {record.token_requests}
                                                            <span className="text-green-600 dark:text-green-400 ml-2">
                                                                ({record.successful_tokens} success)
                                                            </span>
                                                        </div>
                                                    </div>
                                                    
                                                    <div>
                                                        <span className="text-muted-foreground block">Unique Users</span>
                                                        <div className="font-semibold">
                                                            {record.unique_users}
                                                            <span className="text-blue-600 dark:text-blue-400 ml-2">
                                                                (peak: {record.peak_concurrent_users})
                                                            </span>
                                                        </div>
                                                    </div>
                                                    
                                                    <div>
                                                        <span className="text-muted-foreground block">Data Transfer</span>
                                                        <div className="font-semibold">
                                                            {formatBytes(record.bytes_transferred)}
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                {record.error_count > 0 && (
                                                    <div className="mt-3 flex items-center gap-2 text-red-600 dark:text-red-400">
                                                        <AlertCircle className="h-4 w-4" />
                                                        <span className="text-sm">
                                                            {record.error_count} errors occurred
                                                        </span>
                                                    </div>
                                                )}
                                                
                                                {record.average_response_time > 0 && (
                                                    <div className="mt-3 text-sm text-muted-foreground">
                                                        Average response time: {record.average_response_time}ms
                                                    </div>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <div className="text-center py-12">
                                        <BarChart3 className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
                                        <h3 className="text-lg font-semibold">No usage data</h3>
                                        <p className="text-muted-foreground">
                                            No usage records found for this client.
                                        </p>
                                    </div>
                                )}

                                {/* Pagination */}
                                {usage.last_page > 1 && (
                                    <div className="flex items-center justify-between mt-6">
                                        <p className="text-sm text-muted-foreground">
                                            Showing {usage.from} to {usage.to} of {usage.total} records
                                        </p>
                                        <div className="flex items-center gap-2">
                                            {usage.links.map((link, index) => (
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
                    </TabsContent>

                    <TabsContent value="daily">
                        <Card>
                            <CardHeader>
                                <CardTitle>Daily Trends</CardTitle>
                                <CardDescription>API usage trends over time</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="text-center py-12 text-muted-foreground">
                                    Daily trends chart would be implemented here
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="hourly">
                        <Card>
                            <CardHeader>
                                <CardTitle>Hourly Distribution</CardTitle>
                                <CardDescription>Usage patterns throughout the day</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="text-center py-12 text-muted-foreground">
                                    Hourly distribution chart would be implemented here
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>
        </AppLayout>
    );
}