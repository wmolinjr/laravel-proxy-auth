<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table) {
            // Add fields that might be missing for OAuth client management
            
            if (!Schema::hasColumn('oauth_clients', 'website_url')) {
                $table->string('website_url', 500)->nullable()->after('contact_email');
            }
            
            if (!Schema::hasColumn('oauth_clients', 'max_concurrent_tokens')) {
                $table->integer('max_concurrent_tokens')->default(1000)->after('website_url');
            }
            
            if (!Schema::hasColumn('oauth_clients', 'rate_limit_per_minute')) {
                $table->integer('rate_limit_per_minute')->default(100)->after('max_concurrent_tokens');
            }
            
            if (!Schema::hasColumn('oauth_clients', 'health_check_failures')) {
                $table->integer('health_check_failures')->default(0)->after('health_check_interval');
            }

            // Ensure proper default values for existing columns
            if (Schema::hasColumn('oauth_clients', 'is_active')) {
                $table->boolean('is_active')->default(true)->change();
            }
            
            if (Schema::hasColumn('oauth_clients', 'revoked')) {
                $table->boolean('revoked')->default(false)->change();
            }
            
            if (Schema::hasColumn('oauth_clients', 'health_check_enabled')) {
                $table->boolean('health_check_enabled')->default(false)->change();
            }
            
            if (Schema::hasColumn('oauth_clients', 'maintenance_mode')) {
                $table->boolean('maintenance_mode')->default(false)->change();
            }

            // Ensure environment has a default value
            if (Schema::hasColumn('oauth_clients', 'environment')) {
                $table->string('environment', 50)->default('development')->change();
            }

            // Ensure health_status has a default value
            if (Schema::hasColumn('oauth_clients', 'health_status')) {
                $table->string('health_status', 20)->default('unknown')->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table) {
            $columnsToCheck = ['website_url', 'max_concurrent_tokens', 'rate_limit_per_minute', 'health_check_failures'];
            $existingColumns = [];
            
            foreach ($columnsToCheck as $column) {
                if (Schema::hasColumn('oauth_clients', $column)) {
                    $existingColumns[] = $column;
                }
            }
            
            if (!empty($existingColumns)) {
                $table->dropColumn($existingColumns);
            }
        });
    }
};
