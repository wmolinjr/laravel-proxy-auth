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
        // Update oauth_clients table to add admin system columns
        Schema::table('oauth_clients', function (Blueprint $table) {
            // Change id to identifier for consistency with OAuth2 server
            $table->string('identifier', 100)->after('id')->nullable();
            
            // Add additional fields expected by admin system
            $table->text('description')->nullable()->after('name');
            $table->json('redirect_uris')->nullable()->after('description');
            $table->json('grants')->default('["authorization_code"]')->after('redirect_uris');
            $table->json('scopes')->default('["read"]')->after('grants');
            $table->boolean('is_confidential')->default(true)->after('scopes');
        });

        // Update existing records
        DB::statement('UPDATE oauth_clients SET identifier = id WHERE identifier IS NULL');
        
        // Make identifier NOT NULL after populating
        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->string('identifier', 100)->nullable(false)->change();
            $table->unique('identifier');
        });

        // Update oauth_access_tokens table
        Schema::table('oauth_access_tokens', function (Blueprint $table) {
            // Change id to identifier for consistency
            $table->string('identifier', 100)->after('id')->nullable();
            
            // Add revoked_at timestamp
            $table->timestamp('revoked_at')->nullable()->after('revoked');
        });

        // Update existing records
        DB::statement('UPDATE oauth_access_tokens SET identifier = id WHERE identifier IS NULL');
        
        // Make identifier NOT NULL after populating
        Schema::table('oauth_access_tokens', function (Blueprint $table) {
            $table->string('identifier', 100)->nullable(false)->change();
            $table->unique('identifier');
        });

        // Update oauth_authorization_codes table
        Schema::table('oauth_authorization_codes', function (Blueprint $table) {
            // Change id to identifier for consistency
            $table->string('identifier', 100)->after('id')->nullable();
        });

        // Update existing records
        DB::statement('UPDATE oauth_authorization_codes SET identifier = id WHERE identifier IS NULL');
        
        // Make identifier NOT NULL after populating
        Schema::table('oauth_authorization_codes', function (Blueprint $table) {
            $table->string('identifier', 100)->nullable(false)->change();
            $table->unique('identifier');
        });

        // Update oauth_refresh_tokens table
        Schema::table('oauth_refresh_tokens', function (Blueprint $table) {
            // Change id to identifier for consistency
            $table->string('identifier', 100)->after('id')->nullable();
        });

        // Update existing records
        DB::statement('UPDATE oauth_refresh_tokens SET identifier = id WHERE identifier IS NULL');
        
        // Make identifier NOT NULL after populating
        Schema::table('oauth_refresh_tokens', function (Blueprint $table) {
            $table->string('identifier', 100)->nullable(false)->change();
            $table->unique('identifier');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->dropColumn(['identifier', 'description', 'redirect_uris', 'grants', 'scopes', 'is_confidential']);
        });

        Schema::table('oauth_access_tokens', function (Blueprint $table) {
            $table->dropColumn(['identifier', 'revoked_at']);
        });

        Schema::table('oauth_authorization_codes', function (Blueprint $table) {
            $table->dropColumn(['identifier']);
        });

        Schema::table('oauth_refresh_tokens', function (Blueprint $table) {
            $table->dropColumn(['identifier']);
        });
    }
};