import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { adminRoutes } from '@/lib/admin-routes';
import { type NavItem, type User } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { BookOpen, Folder, LayoutGrid, Users, Shield, Key, Settings, BarChart3, AlertTriangle, FileText } from 'lucide-react';
import AppLogo from './app-logo';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

// Admin navigation items
const adminNavItems: NavItem[] = [
    {
        title: 'Admin Dashboard',
        href: adminRoutes.dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Users',
        href: adminRoutes.users.index(),
        icon: Users,
    },
    {
        title: 'OAuth Clients',
        href: adminRoutes.oauthClients.index(),
        icon: Shield,
    },
    {
        title: 'Tokens',
        href: adminRoutes.tokens.index(),
        icon: Key,
    },
    {
        title: 'Security Events',
        href: adminRoutes.securityEvents.index(),
        icon: AlertTriangle,
    },
    {
        title: 'Audit Logs',
        href: adminRoutes.auditLogs.index(),
        icon: FileText,
    },
    {
        title: 'Analytics',
        href: adminRoutes.analytics(),
        icon: BarChart3,
    },
    {
        title: 'Settings',
        href: adminRoutes.settings.index(),
        icon: Settings,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const page = usePage<{ auth: { user: User } }>();
    const isAdmin = page.props.auth?.user?.is_admin || false;

    // Since system is exclusively admin, show admin navigation if user is admin
    const navItems = isAdmin ? adminNavItems : mainNavItems;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={isAdmin ? adminRoutes.dashboard() : dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={navItems} />

                {/* System is exclusively admin, no need for switching */}
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
