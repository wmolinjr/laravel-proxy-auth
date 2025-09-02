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
        Schema::create('oauth_client_events', function (Blueprint $table) {
            $table->id();
            $table->string('client_id', 100);
            $table->unsignedBigInteger('user_id')->nullable();
            
            // Event classification
            $table->string('event_type', 50)->index();
            $table->string('event_name', 100)->index();
            $table->text('description')->nullable();
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('low')->index();
            
            // Request context
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            
            // Timing
            $table->timestamp('occurred_at')->useCurrent()->index();
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('client_id')->references('id')->on('oauth_clients')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            
            // Indexes for performance
            $table->index(['client_id', 'occurred_at']);
            $table->index(['event_type', 'occurred_at']);
            $table->index(['severity', 'occurred_at']);
            $table->index(['user_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oauth_client_events');
    }
};
