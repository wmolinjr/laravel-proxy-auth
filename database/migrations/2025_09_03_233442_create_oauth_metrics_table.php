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
        Schema::create('oauth_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('endpoint')->index(); // e.g., 'token', 'authorize', 'userinfo'
            $table->string('client_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->integer('response_time_ms')->index(); // Response time in milliseconds
            $table->integer('status_code')->index(); // HTTP status code
            $table->string('token_type')->nullable(); // 'access_token', 'refresh_token', 'id_token'
            $table->json('scopes')->nullable(); // OAuth scopes requested
            $table->string('error_type')->nullable()->index(); // Type of error if any
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable(); // Additional context data
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('client_id')->references('identifier')->on('oauth_clients')->onDelete('set null');

            // Composite indexes for common queries
            $table->index(['endpoint', 'created_at']);
            $table->index(['client_id', 'created_at']);
            $table->index(['status_code', 'created_at']);
            $table->index(['response_time_ms', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oauth_metrics');
    }
};
