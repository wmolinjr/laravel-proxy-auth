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
        Schema::create('oauth_client_usages', function (Blueprint $table) {
            $table->id();
            $table->string('client_id', 100);
            $table->date('date');
            
            // Authorization metrics
            $table->integer('authorization_requests')->default(0);
            $table->integer('successful_authorizations')->default(0);
            $table->integer('failed_authorizations')->default(0);
            
            // Token metrics
            $table->integer('token_requests')->default(0);
            $table->integer('successful_tokens')->default(0);
            $table->integer('failed_tokens')->default(0);
            
            // User metrics
            $table->integer('active_users')->default(0);
            $table->integer('unique_users')->default(0);
            
            // API usage metrics
            $table->bigInteger('api_calls')->default(0);
            $table->bigInteger('bytes_transferred')->default(0);
            $table->decimal('average_response_time', 8, 2)->default(0);
            $table->integer('peak_concurrent_users')->default(0);
            
            // Error metrics
            $table->integer('error_count')->default(0);
            
            // Timestamps
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
            
            // Foreign key constraint
            $table->foreign('client_id')->references('id')->on('oauth_clients')->onDelete('cascade');
            
            // Unique constraint - one record per client per date
            $table->unique(['client_id', 'date']);
            
            // Indexes for performance
            $table->index(['date']);
            $table->index(['client_id']);
            $table->index(['last_activity_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oauth_client_usages');
    }
};
