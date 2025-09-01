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
        Schema::table('users', function (Blueprint $table) {
            // Profile information
            $table->string('avatar')->nullable()->after('email');
            $table->string('phone')->nullable()->after('avatar');
            $table->string('department')->nullable()->after('phone');
            $table->string('job_title')->nullable()->after('department');
            
            // Account status and security
            $table->boolean('is_active')->default(true)->after('job_title');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
            $table->timestamp('password_changed_at')->nullable()->after('last_login_at');
            
            // Two-factor authentication
            $table->text('two_factor_secret')->nullable()->after('password_changed_at');
            $table->json('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->boolean('two_factor_enabled')->default(false)->after('two_factor_recovery_codes');
            
            // Admin specific fields
            $table->json('preferences')->nullable()->after('two_factor_enabled'); // UI preferences, settings
            $table->string('timezone', 50)->default('America/Sao_Paulo')->after('preferences');
            $table->string('locale', 10)->default('pt_BR')->after('timezone');
            
            // Soft deletes for admin management
            $table->softDeletes()->after('updated_at');
            
            // Indexes for better performance
            $table->index(['is_active']);
            $table->index(['last_login_at']);
            $table->index(['department']);
            $table->index(['deleted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'avatar', 'phone', 'department', 'job_title',
                'is_active', 'last_login_at', 'password_changed_at',
                'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_enabled',
                'preferences', 'timezone', 'locale', 'deleted_at'
            ]);
        });
    }
};