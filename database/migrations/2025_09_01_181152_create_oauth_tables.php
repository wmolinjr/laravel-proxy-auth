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
        // OAuth Clients - Aplicações que podem usar este IdP
        Schema::create('oauth_clients', function (Blueprint $table) {
            $table->string('id', 100)->primary();
            $table->string('name');
            $table->string('secret', 100)->nullable();
            $table->text('redirect'); // URIs de redirecionamento (comma-separated)
            $table->boolean('personal_access_client')->default(false);
            $table->boolean('password_client')->default(false);
            $table->boolean('revoked')->default(false);
            $table->timestamps();
            
            $table->index(['revoked']);
        });

        // Access Tokens - Tokens de acesso gerados
        Schema::create('oauth_access_tokens', function (Blueprint $table) {
            $table->string('id', 100)->primary();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('client_id', 100);
            $table->string('name')->nullable();
            $table->text('scopes')->nullable();
            $table->boolean('revoked')->default(false);
            $table->timestamps();
            $table->dateTime('expires_at')->nullable();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('client_id')->references('id')->on('oauth_clients')->onDelete('cascade');
            
            $table->index(['user_id']);
            $table->index(['client_id']);
            $table->index(['revoked']);
            $table->index(['expires_at']);
        });

        // Authorization Codes - Códigos de autorização temporários
        Schema::create('oauth_authorization_codes', function (Blueprint $table) {
            $table->string('id', 100)->primary();
            $table->unsignedBigInteger('user_id');
            $table->string('client_id', 100);
            $table->text('scopes')->nullable();
            $table->boolean('revoked')->default(false);
            $table->dateTime('expires_at');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('client_id')->references('id')->on('oauth_clients')->onDelete('cascade');
            
            $table->index(['user_id']);
            $table->index(['client_id']);
            $table->index(['expires_at']);
        });

        // Refresh Tokens - Tokens para renovar access tokens
        Schema::create('oauth_refresh_tokens', function (Blueprint $table) {
            $table->string('id', 100)->primary();
            $table->string('access_token_id', 100);
            $table->boolean('revoked')->default(false);
            $table->dateTime('expires_at');
            $table->timestamps();

            $table->foreign('access_token_id')->references('id')->on('oauth_access_tokens')->onDelete('cascade');
            
            $table->index(['access_token_id']);
            $table->index(['revoked']);
            $table->index(['expires_at']);
        });

        // Personal Access Clients - Clientes para tokens pessoais (se necessário)
        Schema::create('oauth_personal_access_clients', function (Blueprint $table) {
            $table->id();
            $table->string('client_id', 100);
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('oauth_clients')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oauth_personal_access_clients');
        Schema::dropIfExists('oauth_refresh_tokens');
        Schema::dropIfExists('oauth_authorization_codes');
        Schema::dropIfExists('oauth_access_tokens');
        Schema::dropIfExists('oauth_clients');
    }
};