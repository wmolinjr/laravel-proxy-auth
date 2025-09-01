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
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 255)->unique();
            $table->json('value');
            $table->boolean('is_encrypted')->default(false);
            $table->text('description')->nullable();
            $table->string('category', 50)->default('general'); // general, oauth, security, etc.
            $table->boolean('is_public')->default(false); // Can be accessed by non-admin users
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['category']);
            $table->index(['is_public']);
            $table->index(['updated_at']);

            // Foreign key
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};