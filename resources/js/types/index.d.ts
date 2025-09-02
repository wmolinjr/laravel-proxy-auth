import { InertiaLinkProps } from '@inertiajs/react';
import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    sidebarOpen: boolean;
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    avatar_url?: string;
    email_verified_at: string | null;
    department?: string;
    job_title?: string;
    phone?: string;
    is_active: boolean;
    last_login_at?: string;
    password_changed_at?: string;
    two_factor_enabled: boolean;
    roles?: Role[];
    is_admin?: boolean;
    is_super_admin?: boolean;
    created_at: string;
    updated_at: string;
    deleted_at?: string;
    [key: string]: unknown;
}

export interface Role {
    id: number;
    name: string;
    guard_name: string;
    display_name?: string;
    created_at: string;
    updated_at: string;
}

export interface Permission {
    id: number;
    name: string;
    guard_name: string;
    created_at: string;
    updated_at: string;
}

export interface OAuthClient {
    id: number;
    identifier: string;
    name: string;
    description?: string;
    redirect_uris: string[];
    grants: string[];
    scopes: string[];
    is_confidential: boolean;
    is_active: boolean;
    has_secret?: boolean;
    access_tokens_count?: number;
    authorization_codes_count?: number;
    created_at: string;
    updated_at: string;
}

export interface OAuthToken {
    id: number;
    identifier: string;
    user?: User;
    client?: OAuthClient;
    scopes: string[];
    expires_at?: string;
    revoked_at?: string;
    is_valid: boolean;
    is_expired: boolean;
    is_revoked: boolean;
    time_until_expiry?: string;
    created_at: string;
    updated_at: string;
}

export interface AuditLog {
    id: number;
    event_type: string;
    entity_type?: string;
    entity_id?: string;
    user?: User;
    ip_address?: string;
    user_agent?: string;
    old_values?: Record<string, unknown>;
    new_values?: Record<string, unknown>;
    metadata?: Record<string, unknown>;
    created_at: string;
}

export interface SecurityEvent {
    id: number;
    event_type: string;
    event_description?: string;
    severity: 'low' | 'medium' | 'high' | 'critical';
    severity_color?: string;
    user?: User;
    client?: OAuthClient;
    ip_address?: string;
    country_code?: string;
    user_agent?: string;
    details?: Record<string, unknown>;
    is_resolved: boolean;
    resolved_at?: string;
    resolved_by?: User;
    resolution_notes?: string;
    created_at: string;
}

export interface SystemSetting {
    id: number;
    key: string;
    value: unknown;
    is_encrypted: boolean;
    description?: string;
    category: string;
    is_public: boolean;
    updated_by?: User;
    created_at: string;
    updated_at: string;
}

export interface DashboardStats {
    users: {
        total: number;
        active: number;
        new_today: number;
        growth_percentage: number;
    };
    oauth_clients: {
        total: number;
        active: number;
    };
    tokens: {
        active: number;
        issued_today: number;
    };
    security: {
        events_today: number;
        unresolved_events: number;
        high_severity: number;
    };
    audit_logs: {
        today: number;
        this_week: number;
    };
}

export interface AnalyticsData {
    users: {
        total: number;
        active: number;
        admins: number;
        recent_registrations: number;
        growth_chart: Array<{ date: string; users: number }>;
    };
    oauth: {
        clients: number;
        active_tokens: number;
        token_usage_chart: Array<{ date: string; tokens: number }>;
        client_usage: Array<{ name: string; active_tokens: number }>;
    };
    security: {
        events_last_30_days: number;
        unresolved_events: number;
        high_severity_events: number;
        security_chart: Array<{ date: string; events: number }>;
        events_by_type: Record<string, number>;
    };
    system: {
        audit_logs_last_30_days: number;
        database_size: string;
        uptime: string;
    };
}

export interface PaginatedResponse<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
}
