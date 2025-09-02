<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for performance optimization
     */
    public function up(): void
    {
        // OAuth Clients table optimizations
        Schema::table('oauth_clients', function (Blueprint $table) {
            // Index for health status filtering and monitoring
            $table->index(['health_status', 'health_check_enabled'], 'idx_clients_health_monitoring');
            
            // Index for active clients filtering
            $table->index(['is_active', 'revoked'], 'idx_clients_active_status');
            
            // Index for environment-based filtering
            $table->index(['environment', 'is_active'], 'idx_clients_environment');
            
            // Index for maintenance mode queries
            $table->index(['maintenance_mode', 'is_active'], 'idx_clients_maintenance');
            
            // Composite index for dashboard overview queries
            $table->index(['is_active', 'health_status', 'environment'], 'idx_clients_dashboard_overview');
            
            // Index for health check scheduling
            $table->index(['health_check_enabled', 'last_health_check'], 'idx_clients_health_schedule');
        });

        // OAuth Client Usage table optimizations
        if (Schema::hasTable('oauth_client_usage')) {
            Schema::table('oauth_client_usage', function (Blueprint $table) {
                // Primary lookup index for client analytics
                $table->index(['client_id', 'date'], 'idx_usage_client_date');
                
                // Index for date range queries
                $table->index(['date', 'client_id'], 'idx_usage_date_range');
                
                // Index for performance monitoring queries
                $table->index(['date', 'total_requests'], 'idx_usage_performance');
                
                // Index for error rate analysis
                $table->index(['client_id', 'failed_requests', 'total_requests'], 'idx_usage_error_analysis');
            });
        }

        // OAuth Notifications table optimizations
        Schema::table('oauth_notifications', function (Blueprint $table) {
            // Index for unacknowledged notifications (notification center)
            $table->index(['acknowledged_at', 'created_at'], 'idx_notifications_unacknowledged');
            
            // Index for client-specific notifications
            $table->index(['oauth_client_id', 'created_at'], 'idx_notifications_client');
            
            // Index for notification type filtering
            $table->index(['type', 'acknowledged_at'], 'idx_notifications_type');
            
            // Index for recent notifications dashboard
            $table->index(['created_at', 'acknowledged_at'], 'idx_notifications_recent');
            
            // Composite index for dashboard notification stats
            $table->index(['type', 'acknowledged_at', 'created_at'], 'idx_notifications_dashboard_stats');
        });

        // OAuth Alert Rules table optimizations
        Schema::table('oauth_alert_rules', function (Blueprint $table) {
            // Index for active rules lookup
            $table->index(['is_active', 'trigger_type'], 'idx_alert_rules_active');
            
            // Index for cooldown queries
            $table->index(['last_triggered_at', 'cooldown_minutes'], 'idx_alert_rules_cooldown');
        });

        // OAuth Access Tokens optimizations (if table exists)
        if (Schema::hasTable('oauth_access_tokens')) {
            Schema::table('oauth_access_tokens', function (Blueprint $table) {
                // Index for client token management
                if (!$this->indexExists('oauth_access_tokens', 'idx_tokens_client_revoked')) {
                    $table->index(['client_id', 'revoked'], 'idx_tokens_client_revoked');
                }
                
                // Index for token cleanup and expiration
                if (!$this->indexExists('oauth_access_tokens', 'idx_tokens_expires_at')) {
                    $table->index(['expires_at', 'revoked'], 'idx_tokens_expires_at');
                }
                
                // Index for user tokens
                if (!$this->indexExists('oauth_access_tokens', 'idx_tokens_user_client')) {
                    $table->index(['user_id', 'client_id'], 'idx_tokens_user_client');
                }
            });
        }

        // OAuth Authorization Codes optimizations (if table exists)
        if (Schema::hasTable('oauth_auth_codes')) {
            Schema::table('oauth_auth_codes', function (Blueprint $table) {
                // Index for client authorization codes
                if (!$this->indexExists('oauth_auth_codes', 'idx_auth_codes_client')) {
                    $table->index(['client_id', 'revoked'], 'idx_auth_codes_client');
                }
                
                // Index for code cleanup
                if (!$this->indexExists('oauth_auth_codes', 'idx_auth_codes_expires_at')) {
                    $table->index(['expires_at', 'revoked'], 'idx_auth_codes_expires_at');
                }
            });
        }

        // Add indexes for audit logs if table exists
        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                // Index for user activity tracking
                if (!$this->indexExists('audit_logs', 'idx_audit_user_created')) {
                    $table->index(['user_id', 'created_at'], 'idx_audit_user_created');
                }
                
                // Index for event type filtering
                if (!$this->indexExists('audit_logs', 'idx_audit_event_created')) {
                    $table->index(['event_type', 'created_at'], 'idx_audit_event_created');
                }
            });
        }

        // Add indexes for system settings if table exists
        if (Schema::hasTable('system_settings')) {
            Schema::table('system_settings', function (Blueprint $table) {
                // Index for settings lookup
                if (!$this->indexExists('system_settings', 'idx_settings_key')) {
                    $table->index(['key'], 'idx_settings_key');
                }
            });
        }
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        // OAuth Clients indexes
        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->dropIndex('idx_clients_health_monitoring');
            $table->dropIndex('idx_clients_active_status');
            $table->dropIndex('idx_clients_environment');
            $table->dropIndex('idx_clients_maintenance');
            $table->dropIndex('idx_clients_dashboard_overview');
            $table->dropIndex('idx_clients_health_schedule');
        });

        // OAuth Client Usage indexes
        if (Schema::hasTable('oauth_client_usage')) {
            Schema::table('oauth_client_usage', function (Blueprint $table) {
                $table->dropIndex('idx_usage_client_date');
                $table->dropIndex('idx_usage_date_range');
                $table->dropIndex('idx_usage_performance');
                $table->dropIndex('idx_usage_error_analysis');
            });
        }

        // OAuth Notifications indexes
        Schema::table('oauth_notifications', function (Blueprint $table) {
            $table->dropIndex('idx_notifications_unacknowledged');
            $table->dropIndex('idx_notifications_client');
            $table->dropIndex('idx_notifications_type');
            $table->dropIndex('idx_notifications_recent');
            $table->dropIndex('idx_notifications_dashboard_stats');
        });

        // OAuth Alert Rules indexes
        Schema::table('oauth_alert_rules', function (Blueprint $table) {
            $table->dropIndex('idx_alert_rules_active');
            $table->dropIndex('idx_alert_rules_cooldown');
        });

        // OAuth Access Tokens indexes
        if (Schema::hasTable('oauth_access_tokens')) {
            Schema::table('oauth_access_tokens', function (Blueprint $table) {
                if ($this->indexExists('oauth_access_tokens', 'idx_tokens_client_revoked')) {
                    $table->dropIndex('idx_tokens_client_revoked');
                }
                if ($this->indexExists('oauth_access_tokens', 'idx_tokens_expires_at')) {
                    $table->dropIndex('idx_tokens_expires_at');
                }
                if ($this->indexExists('oauth_access_tokens', 'idx_tokens_user_client')) {
                    $table->dropIndex('idx_tokens_user_client');
                }
            });
        }

        // OAuth Authorization Codes indexes
        if (Schema::hasTable('oauth_auth_codes')) {
            Schema::table('oauth_auth_codes', function (Blueprint $table) {
                if ($this->indexExists('oauth_auth_codes', 'idx_auth_codes_client')) {
                    $table->dropIndex('idx_auth_codes_client');
                }
                if ($this->indexExists('oauth_auth_codes', 'idx_auth_codes_expires_at')) {
                    $table->dropIndex('idx_auth_codes_expires_at');
                }
            });
        }

        // Audit logs indexes
        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                if ($this->indexExists('audit_logs', 'idx_audit_user_created')) {
                    $table->dropIndex('idx_audit_user_created');
                }
                if ($this->indexExists('audit_logs', 'idx_audit_event_created')) {
                    $table->dropIndex('idx_audit_event_created');
                }
            });
        }

        // System settings indexes
        if (Schema::hasTable('system_settings')) {
            Schema::table('system_settings', function (Blueprint $table) {
                if ($this->indexExists('system_settings', 'idx_settings_key')) {
                    $table->dropIndex('idx_settings_key');
                }
            });
        }
    }

    /**
     * Check if index exists on a table
     */
    private function indexExists(string $table, string $index): bool
    {
        try {
            $connection = Schema::getConnection();
            $indexName = $connection->getTablePrefix() . $index;
            
            // For PostgreSQL, check information_schema
            if ($connection->getDriverName() === 'pgsql') {
                $result = $connection->select(
                    "SELECT indexname FROM pg_indexes WHERE tablename = ? AND indexname = ?",
                    [$table, $indexName]
                );
                return count($result) > 0;
            }
            
            // For other databases, try to use raw SQL
            $result = $connection->select(
                "SHOW INDEX FROM {$table} WHERE Key_name = ?",
                [$indexName]
            );
            return count($result) > 0;
        } catch (\Exception $e) {
            // If we can't check, assume it doesn't exist
            return false;
        }
    }
};