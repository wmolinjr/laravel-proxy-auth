<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table) {
            // Health monitoring fields
            $table->string('health_check_url')->nullable()->after('is_confidential');
            $table->integer('health_check_interval')->default(300)->after('health_check_url'); // seconds
            $table->boolean('health_check_enabled')->default(false)->after('health_check_interval');
            $table->timestamp('last_health_check')->nullable()->after('health_check_enabled');
            $table->enum('health_status', ['unknown', 'healthy', 'unhealthy', 'error'])->default('unknown')->after('last_health_check');
            $table->text('last_error_message')->nullable()->after('health_status');
            
            // Activity tracking
            $table->timestamp('last_activity_at')->nullable()->after('last_error_message');
            $table->boolean('is_active')->default(true)->after('last_activity_at');
            
            // Maintenance mode
            $table->boolean('maintenance_mode')->default(false)->after('is_active');
            $table->text('maintenance_message')->nullable()->after('maintenance_mode');
            
            // Environment and metadata
            $table->enum('environment', ['production', 'staging', 'development'])->default('production')->after('maintenance_message');
            $table->json('tags')->nullable()->after('environment');
            $table->string('contact_email')->nullable()->after('tags');
            $table->string('version')->nullable()->after('contact_email');
            
            // User tracking
            $table->unsignedBigInteger('created_by')->nullable()->after('version');
            $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');
            
            // Add indexes for performance
            $table->index(['health_status']);
            $table->index(['is_active']);
            $table->index(['maintenance_mode']);
            $table->index(['environment']);
            $table->index(['last_activity_at']);
            $table->index(['last_health_check']);
            
            // Foreign key constraints
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table) {
            // Drop foreign keys first
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            
            // Drop indexes
            $table->dropIndex(['health_status']);
            $table->dropIndex(['is_active']);
            $table->dropIndex(['maintenance_mode']);
            $table->dropIndex(['environment']);
            $table->dropIndex(['last_activity_at']);
            $table->dropIndex(['last_health_check']);
            
            // Drop columns
            $table->dropColumn([
                'health_check_url',
                'health_check_interval',
                'health_check_enabled',
                'last_health_check',
                'health_status',
                'last_error_message',
                'last_activity_at',
                'is_active',
                'maintenance_mode',
                'maintenance_message',
                'environment',
                'tags',
                'contact_email',
                'version',
                'created_by',
                'updated_by'
            ]);
        });
    }
};
