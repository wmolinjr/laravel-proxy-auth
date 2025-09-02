<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Skip creating notifications table if it already exists (Laravel default)
        if (!Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->json('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }

        // OAuth-specific alert rules and notification preferences
        Schema::create('oauth_alert_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->enum('trigger_type', [
                'health_check_failure',
                'high_error_rate',
                'response_time_threshold',
                'token_usage_spike',
                'maintenance_mode',
                'security_event',
                'client_inactive'
            ]);
            $table->json('conditions'); // JSON with threshold values, comparison operators
            $table->json('notification_channels'); // email, slack, webhook, in_app
            $table->json('recipients'); // user IDs, email addresses, slack channels
            $table->boolean('is_active')->default(true);
            $table->integer('cooldown_minutes')->default(60); // Prevent spam
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();

            $table->index(['trigger_type', 'is_active']);
        });

        // Notification history for tracking and analytics
        Schema::create('oauth_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('oauth_client_id')->nullable();
            $table->foreign('oauth_client_id')->references('id')->on('oauth_clients')->onDelete('cascade');
            $table->foreignId('alert_rule_id')->nullable()->constrained('oauth_alert_rules')->onDelete('set null');
            $table->string('type'); // alert, info, warning, critical
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable(); // Additional context data
            $table->json('channels_sent'); // Which channels were used
            $table->json('recipients'); // Who received the notification
            $table->enum('status', ['pending', 'sent', 'failed', 'acknowledged'])->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('acknowledgment_note')->nullable();
            $table->timestamps();

            $table->index(['oauth_client_id', 'type', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        // Notification channels configuration
        Schema::create('notification_channels', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Email, Slack, Webhook, SMS
            $table->string('type'); // email, slack, webhook, sms
            $table->json('configuration'); // Channel-specific config
            $table->boolean('is_active')->default(true);
            $table->integer('rate_limit')->default(60); // Max notifications per hour
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['name', 'type']);
        });

        // User notification preferences
        Schema::create('user_notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('notification_type'); // Same as trigger_type
            $table->json('preferred_channels'); // email, slack, in_app
            $table->boolean('enabled')->default(true);
            $table->json('schedule')->nullable(); // Business hours, timezone preferences
            $table->timestamps();

            $table->unique(['user_id', 'notification_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notification_preferences');
        Schema::dropIfExists('notification_channels');
        Schema::dropIfExists('oauth_notifications');
        Schema::dropIfExists('oauth_alert_rules');
        Schema::dropIfExists('notifications');
    }
};