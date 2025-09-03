<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\OAuth\OAuthClient;

class OAuthClientSeeder extends Seeder
{
    public function run()
    {
        // Cliente para Apache mod_auth_openidc
        OAuthClient::updateOrCreate(
            ['id' => 'apache-studio-client'],
            [
                'name' => 'Studio WMJ Apache Client',
                'secret' => '$2y$10$' . hash('sha256', 'apache-studio-secret-' . config('app.key')),
                'redirect' => 'https://studio.wmj.com.br/oidc-redirect',
                'personal_access_client' => false,
                'password_client' => false,
                'revoked' => false,
                'provider' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Cliente para testes de desenvolvimento
        OAuthClient::updateOrCreate(
            ['id' => 'test-client'],
            [
                'name' => 'Test Client',
                'secret' => '$2y$10$' . hash('sha256', 'test-secret-' . config('app.key')),
                'redirect' => 'https://auth.wmj.com.br/auth/callback',
                'personal_access_client' => false,
                'password_client' => false,
                'revoked' => false,
                'provider' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->command->info('OAuth clients created successfully!');
        $this->command->info('Apache Client ID: apache-studio-client');
        $this->command->info('Apache Client Secret: (hashed)');
        $this->command->info('Apache Redirect URI: https://studio.wmj.com.br/oidc-redirect');
    }
}