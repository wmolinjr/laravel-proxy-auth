import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { adminRoutes } from '@/lib/admin-routes';
import { type BreadcrumbItem, type OAuthClient } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { BarChart3, TrendingUp, Users, Zap } from 'lucide-react';

interface OAuthClientAnalyticsProps {
    client: OAuthClient;
    analytics: {
        daily_usage: Array<{
            date: string;
            authorization_requests: number;
            successful_authorizations: number;
            token_requests: number;
            successful_tokens: number;
            api_calls: number;
            unique_users: number;
            error_count: number;
        }>;
        summary: {
            total_requests: number;
            success_rate: number;
            peak_concurrent_users: number;
            average_response_time: number;
        };
        top_endpoints: Array<{
            endpoint: string;
            calls: number;
        }>;
        error_breakdown: Array<{
            error_type: string;
            count: number;
        }>;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: adminRoutes.dashboard() },
    { title: 'OAuth Clients', href: adminRoutes.oauthClients.index() },
];

export default function OAuthClientAnalytics({ client, analytics }: OAuthClientAnalyticsProps) {
    const currentBreadcrumbs = [
        ...breadcrumbs,
        { title: client.name, href: adminRoutes.oauthClients.show(client.id) },
        { title: 'Analytics', href: adminRoutes.oauthClients.analytics(client.id) },
    ];

    return (
        <AppLayout breadcrumbs={currentBreadcrumbs}>
            <Head title={`Analytics - ${client.name}`} />
            
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Analytics</h1>
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
                            <CardTitle className="text-sm font-medium">Total Requests</CardTitle>
                            <Zap className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {analytics.summary.total_requests.toLocaleString()}
                            </div>
                        </CardContent>
                    </Card>
                    
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Success Rate</CardTitle>
                            <TrendingUp className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {analytics.summary.success_rate.toFixed(1)}%
                            </div>
                        </CardContent>
                    </Card>
                    
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Peak Users</CardTitle>
                            <Users className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {analytics.summary.peak_concurrent_users}
                            </div>
                        </CardContent>
                    </Card>
                    
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Avg Response</CardTitle>
                            <BarChart3 className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {analytics.summary.average_response_time}ms
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Charts Section */}
                <div className="grid gap-6 md:grid-cols-2">
                    {/* Daily Usage */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Daily Usage (Last 30 Days)</CardTitle>
                            <CardDescription>API calls and user activity</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                {analytics.daily_usage.slice(0, 7).map((day) => (
                                    <div key={day.date} className="flex items-center justify-between">
                                        <div className="flex flex-col">
                                            <span className="text-sm font-medium">
                                                {new Date(day.date).toLocaleDateString()}
                                            </span>
                                            <span className="text-xs text-muted-foreground">
                                                {day.unique_users} users
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-4 text-sm">
                                            <span>{day.api_calls} calls</span>
                                            <span className={day.error_count > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'}>
                                                {day.error_count} errors
                                            </span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Top Endpoints */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Top Endpoints</CardTitle>
                            <CardDescription>Most frequently accessed endpoints</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                {analytics.top_endpoints.map((endpoint, index) => (
                                    <div key={endpoint.endpoint} className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <span className="text-sm font-medium">#{index + 1}</span>
                                            <code className="text-xs bg-muted px-2 py-1 rounded">
                                                {endpoint.endpoint}
                                            </code>
                                        </div>
                                        <span className="text-sm">
                                            {endpoint.calls.toLocaleString()} calls
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Error Breakdown */}
                {analytics.error_breakdown.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Error Breakdown</CardTitle>
                            <CardDescription>Most common error types</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="grid gap-4 md:grid-cols-2">
                                {analytics.error_breakdown.map((error) => (
                                    <div key={error.error_type} className="flex items-center justify-between p-3 border rounded">
                                        <span className="text-sm font-medium">{error.error_type}</span>
                                        <span className="text-sm text-red-600 dark:text-red-400 font-semibold">
                                            {error.count} occurrences
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}