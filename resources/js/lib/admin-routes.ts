// Admin route helpers - manually created since routes are not auto-generated yet
export const adminRoutes = {
  // Dashboard
  dashboard: () => '/dashboard',
  analytics: () => '/analytics',

  // User Management
  users: {
    index: () => '/users',
    create: () => '/users/create',
    show: (id: number) => `/users/${id}`,
    edit: (id: number) => `/users/${id}/edit`,
    store: () => '/users',
    update: (id: number) => `/users/${id}`,
    destroy: (id: number) => `/users/${id}`,
    restore: (id: number) => `/users/${id}/restore`,
    forceDelete: (id: number) => `/users/${id}/force-delete`,
  },

  // OAuth Client Management
  oauthClients: {
    index: () => '/oauth-clients',
    create: () => '/oauth-clients/create',
    show: (id: number) => `/oauth-clients/${id}`,
    edit: (id: number) => `/oauth-clients/${id}/edit`,
    store: () => '/oauth-clients',
    update: (id: number) => `/oauth-clients/${id}`,
    destroy: (id: number) => `/oauth-clients/${id}`,
    regenerateSecret: (id: number) => `/oauth-clients/${id}/regenerate-secret`,
    revokeTokens: (id: number) => `/oauth-clients/${id}/revoke-tokens`,
    healthCheck: (id: number) => `/oauth-clients/${id}/health-check`,
    toggleStatus: (id: number) => `/oauth-clients/${id}/toggle-status`,
    toggleMaintenance: (id: number) => `/oauth-clients/${id}/toggle-maintenance`,
    analytics: (id: number) => `/oauth-clients/${id}/analytics`,
    events: (id: number) => `/oauth-clients/${id}/events`,
  },

  // Token Management
  tokens: {
    index: () => '/tokens',
    show: (id: number) => `/tokens/${id}`,
    destroy: (id: number) => `/tokens/${id}`,
    revoke: () => '/tokens/revoke',
    revokeAll: () => '/tokens/revoke-all',
    cleanup: () => '/tokens/cleanup',
  },

  // Audit Logs
  auditLogs: {
    index: () => '/audit-logs',
    export: () => '/audit-logs/export',
  },

  // Security Events
  securityEvents: {
    index: () => '/security-events',
    resolve: (id: number) => `/security-events/${id}/resolve`,
  },

  // System Settings
  settings: {
    index: () => '/settings',
    update: () => '/settings/update',
  },
} as const;

// Utility functions - currently not used but available for future implementation

// Utility function to check if current route is admin (now all routes are admin)
export const isAdminRoute = (): boolean => {
  return true; // All routes are now admin routes
};

// Get admin section from URL - placeholder for future implementation  
export const getAdminSection = (): string | null => {
  return null; // Future implementation
};