<?php

namespace Database\Seeders;

use App\Models\OAuthAlertRule;
use App\Models\User;
use Illuminate\Database\Seeder;

class OAuthAlertRulesSeeder extends Seeder
{
    public function run(): void
    {
        // Get admin users for notifications
        $adminEmails = User::whereHas('roles', function ($query) {
            $query->where('name', 'super_admin')
                  ->orWhere('name', 'admin');
        })->pluck('email')->toArray();

        if (empty($adminEmails)) {
            // Fallback to all users if no admin role exists
            $adminEmails = User::pluck('email')->toArray();
        }

        // Health Check Failure Rules
        OAuthAlertRule::firstOrCreate(
            ['name' => 'Critical Health Check Failure'],
            [
                'description' => 'Triggered when OAuth client fails health check 3+ times consecutively',
                'trigger_type' => 'health_check_failure',
                'conditions' => [
                    ['field' => 'consecutive_failures', 'operator' => '>=', 'threshold' => 3]
                ],
                'notification_channels' => ['email', 'in_app'],
                'recipients' => $adminEmails,
                'cooldown_minutes' => 30,
                'is_active' => true,
            ]
        );

        OAuthAlertRule::firstOrCreate(
            ['name' => 'Extended Health Check Failure'],
            [
                'description' => 'Triggered when OAuth client fails health check 10+ times consecutively',
                'trigger_type' => 'health_check_failure',
                'conditions' => [
                    ['field' => 'consecutive_failures', 'operator' => '>=', 'threshold' => 10]
                ],
                'notification_channels' => ['email', 'in_app'],
                'recipients' => $adminEmails,
                'cooldown_minutes' => 60,
                'is_active' => true,
            ]
        );

        // Error Rate Rules
        OAuthAlertRule::firstOrCreate(
            ['name' => 'High Error Rate Warning'],
            [
                'description' => 'Triggered when error rate exceeds 10%',
                'trigger_type' => 'high_error_rate',
                'conditions' => [
                    ['field' => 'error_rate_percent', 'operator' => '>', 'threshold' => 10]
                ],
                'notification_channels' => ['email', 'in_app'],
                'recipients' => $adminEmails,
                'cooldown_minutes' => 15,
                'is_active' => true,
            ]
        );

        OAuthAlertRule::firstOrCreate(
            ['name' => 'Critical Error Rate Alert'],
            [
                'description' => 'Triggered when error rate exceeds 25%',
                'trigger_type' => 'high_error_rate',
                'conditions' => [
                    ['field' => 'error_rate_percent', 'operator' => '>', 'threshold' => 25]
                ],
                'notification_channels' => ['email', 'in_app'],
                'recipients' => $adminEmails,
                'cooldown_minutes' => 10,
                'is_active' => true,
            ]
        );

        // Response Time Rules
        OAuthAlertRule::firstOrCreate(
            ['name' => 'Slow Response Time Warning'],
            [
                'description' => 'Triggered when average response time exceeds 2 seconds',
                'trigger_type' => 'response_time_threshold',
                'conditions' => [
                    ['field' => 'avg_response_time', 'operator' => '>', 'threshold' => 2000]
                ],
                'notification_channels' => ['email'],
                'recipients' => $adminEmails,
                'cooldown_minutes' => 30,
                'is_active' => true,
            ]
        );

        OAuthAlertRule::firstOrCreate(
            ['name' => 'Very Slow Response Time Alert'],
            [
                'description' => 'Triggered when average response time exceeds 5 seconds',
                'trigger_type' => 'response_time_threshold',
                'conditions' => [
                    ['field' => 'avg_response_time', 'operator' => '>', 'threshold' => 5000]
                ],
                'notification_channels' => ['email', 'in_app'],
                'recipients' => $adminEmails,
                'cooldown_minutes' => 15,
                'is_active' => true,
            ]
        );

        // Token Usage Spike
        OAuthAlertRule::firstOrCreate(
            ['name' => 'Token Usage Spike'],
            [
                'description' => 'Triggered when token requests spike by 200% compared to normal',
                'trigger_type' => 'token_usage_spike',
                'conditions' => [
                    ['field' => 'usage_spike_percent', 'operator' => '>', 'threshold' => 200]
                ],
                'notification_channels' => ['email', 'in_app'],
                'recipients' => $adminEmails,
                'cooldown_minutes' => 60,
                'is_active' => true,
            ]
        );

        // Maintenance Mode Notifications
        OAuthAlertRule::firstOrCreate(
            ['name' => 'Maintenance Mode Changes'],
            [
                'description' => 'Triggered when client enters or exits maintenance mode',
                'trigger_type' => 'maintenance_mode',
                'conditions' => [
                    ['field' => 'maintenance_mode', 'operator' => 'in', 'threshold' => [true, false]]
                ],
                'notification_channels' => ['email', 'in_app'],
                'recipients' => $adminEmails,
                'cooldown_minutes' => 5,
                'is_active' => true,
            ]
        );

        // Security Events
        OAuthAlertRule::firstOrCreate(
            ['name' => 'Security Event Alert'],
            [
                'description' => 'Triggered when security events are detected',
                'trigger_type' => 'security_event',
                'conditions' => [
                    ['field' => 'severity', 'operator' => 'in', 'threshold' => ['high', 'critical']]
                ],
                'notification_channels' => ['email', 'in_app'],
                'recipients' => $adminEmails,
                'cooldown_minutes' => 1,
                'is_active' => true,
            ]
        );

        // Client Inactivity
        OAuthAlertRule::firstOrCreate(
            ['name' => 'Client Inactive Alert'],
            [
                'description' => 'Triggered when client has no activity for 24+ hours',
                'trigger_type' => 'client_inactive',
                'conditions' => [
                    ['field' => 'hours_inactive', 'operator' => '>=', 'threshold' => 24]
                ],
                'notification_channels' => ['email'],
                'recipients' => $adminEmails,
                'cooldown_minutes' => 1440, // 24 hours
                'is_active' => true,
            ]
        );

        $this->command->info('✅ Created ' . OAuthAlertRule::count() . ' OAuth alert rules');
    }
}