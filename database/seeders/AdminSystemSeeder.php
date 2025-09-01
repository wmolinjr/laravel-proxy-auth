<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdminSystemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create permissions
        $permissions = [
            // User Management
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',
            'users.manage_roles',
            
            // OAuth Client Management
            'oauth_clients.view',
            'oauth_clients.create',
            'oauth_clients.edit',
            'oauth_clients.delete',
            'oauth_clients.regenerate_secret',
            
            // Token Management
            'tokens.view',
            'tokens.revoke',
            'tokens.create',
            
            // System Settings
            'system_settings.view',
            'system_settings.edit',
            
            // Audit Logs
            'audit_logs.view',
            'audit_logs.export',
            
            // Security Events
            'security_events.view',
            'security_events.resolve',
            
            // Dashboard & Analytics
            'dashboard.view',
            'analytics.view',
            
            // Admin Management
            'admin.full_access',
        ];

        foreach ($permissions as $permission) {
            \Spatie\Permission\Models\Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web'
            ]);
        }

        // Create roles
        $superAdminRole = \Spatie\Permission\Models\Role::firstOrCreate([
            'name' => 'super-admin',
            'guard_name' => 'web'
        ]);

        $adminRole = \Spatie\Permission\Models\Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web'
        ]);

        $userRole = \Spatie\Permission\Models\Role::firstOrCreate([
            'name' => 'user',
            'guard_name' => 'web'
        ]);

        // Assign all permissions to super-admin
        $superAdminRole->syncPermissions($permissions);

        // Assign limited permissions to admin (exclude full admin access)
        $adminPermissions = array_filter($permissions, fn($p) => $p !== 'admin.full_access');
        $adminRole->syncPermissions($adminPermissions);

        // Create super admin user if it doesn't exist
        $superAdmin = \App\Models\User::firstOrCreate([
            'email' => 'admin@wmj.com.br'
        ], [
            'name' => 'Super Administrator',
            'password' => 'admin123',
            'email_verified_at' => now(),
            'is_active' => true,
            'department' => 'IT',
            'job_title' => 'System Administrator',
            'password_changed_at' => now(),
            'two_factor_enabled' => false,
        ]);

        // Assign super-admin role
        $superAdmin->assignRole('super-admin');

        // Create initial system settings
        \App\Models\Admin\SystemSetting::updateOrCreate(
            ['key' => 'app.name'],
            [
                'value' => 'WMJ Authentication Server', 
                'category' => 'general',
                'description' => 'Application display name',
                'is_public' => true
            ]
        );

        \App\Models\Admin\SystemSetting::updateOrCreate(
            ['key' => 'security.require_2fa_for_admins'],
            [
                'value' => false, 
                'category' => 'security',
                'description' => 'Require 2FA authentication for admin users',
                'is_public' => false
            ]
        );

        \App\Models\Admin\SystemSetting::updateOrCreate(
            ['key' => 'security.max_login_attempts'],
            [
                'value' => 5, 
                'category' => 'security',
                'description' => 'Maximum failed login attempts before lockout',
                'is_public' => false
            ]
        );

        \App\Models\Admin\SystemSetting::updateOrCreate(
            ['key' => 'security.lockout_duration'],
            [
                'value' => 15, 
                'category' => 'security',
                'description' => 'Account lockout duration in minutes',
                'is_public' => false
            ]
        );

        \App\Models\Admin\SystemSetting::updateOrCreate(
            ['key' => 'oauth.default_token_lifetime'],
            [
                'value' => 3600, 
                'category' => 'oauth',
                'description' => 'Default access token lifetime in seconds',
                'is_public' => false
            ]
        );

        \App\Models\Admin\SystemSetting::updateOrCreate(
            ['key' => 'oauth.refresh_token_lifetime'],
            [
                'value' => 2592000, 
                'category' => 'oauth',
                'description' => 'Refresh token lifetime in seconds (30 days)',
                'is_public' => false
            ]
        );

        \App\Models\Admin\SystemSetting::updateOrCreate(
            ['key' => 'logging.retention_days'],
            [
                'value' => 90, 
                'category' => 'general',
                'description' => 'Number of days to retain audit logs',
                'is_public' => false
            ]
        );

        // Log seeder execution
        \App\Models\Admin\AuditLog::logEvent(
            'system_seeded',
            'System',
            null,
            null,
            [
                'permissions_created' => count($permissions),
                'roles_created' => 3,
                'super_admin_created' => $superAdmin->wasRecentlyCreated,
                'system_settings_initialized' => 7
            ]
        );

        $this->command->info('Admin system seeded successfully!');
        $this->command->info('Super Admin credentials:');
        $this->command->info('Email: admin@wmj.com.br');
        $this->command->info('Password: admin123');
        $this->command->warn('Please change the default password after first login!');
    }
}
