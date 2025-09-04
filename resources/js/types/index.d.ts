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

export interface OAuthClient extends Record<string, unknown> {
    id: number | string;
    identifier: string;
    name: string;
    description?: string;
    redirect_uris: string[];
    grants: string[];
    scopes: string[];
    is_confidential: boolean;
    is_active: boolean;
    is_revoked?: boolean;
    has_secret?: boolean;
    access_tokens_count?: number;
    authorization_codes_count?: number;
    created_at: string;
    updated_at: string;
    // Enhanced monitoring fields
    health_check_url?: string;
    health_check_interval: number;
    health_check_enabled: boolean;
    health_status: 'unknown' | 'healthy' | 'unhealthy' | 'error';
    last_health_check_at?: string;
    health_check_failures: number;
    maintenance_mode: boolean;
    maintenance_message?: string;
    maintenance_reason?: string;
    environment: 'production' | 'staging' | 'development';
    contact_email?: string;
    owner_contact?: string;
    technical_contact?: string;
    max_concurrent_tokens: number;
    rate_limit_per_minute: number;
    tags?: string[] | string;
    website_url?: string;
    documentation_url?: string;
    privacy_policy_url?: string;
    terms_of_service_url?: string;
    logo_url?: string;
    version?: string;
    // Audit fields
    created_by?: number;
    updated_by?: number;
    creator?: User;
    updater?: User;
    // Computed properties
    usage_stats?: OAuthClientUsage;
    recent_events?: OAuthClientEvent[];
    needs_health_check?: boolean;
}

export interface OAuthClientUsage {
    id: number;
    client_id: number;
    date: string;
    authorization_requests: number;
    successful_authorizations: number;
    failed_authorizations: number;
    token_requests: number;
    successful_tokens: number;
    failed_tokens: number;
    active_users: number;
    unique_users: number;
    api_calls: number;
    bytes_transferred: number;
    average_response_time: number;
    peak_concurrent_users: number;
    error_count: number;
    last_activity_at?: string;
    created_at: string;
    updated_at: string;
}

export interface OAuthClientEvent {
    id: number;
    client_id: number;
    event_type: string;
    event_name: string;
    event_description?: string;
    severity: 'info' | 'warning' | 'error' | 'critical';
    severity_color?: string;
    data?: Record<string, unknown>;
    occurred_at: string;
    ip_address?: string;
    user_agent?: string;
    user_id?: number;
    is_resolved: boolean;
    resolved_at?: string;
    resolved_by_id?: number;
    resolution_notes?: string;
    created_at: string;
    updated_at: string;
    // Relationships
    oauth_client?: OAuthClient;
    user?: User;
    resolved_by?: User;
}

export interface OAuthToken extends Record<string, unknown> {
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

export interface AuditLog extends Record<string, unknown> {
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

export interface SecurityEvent extends Record<string, unknown> {
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
