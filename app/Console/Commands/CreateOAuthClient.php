<?php

namespace App\Console\Commands;

use App\Models\OAuth\OAuthClient;
use Illuminate\Console\Command;

class CreateOAuthClient extends Command
{
    protected $signature = 'oauth:create-client {id} {name} {redirect} {--secret=}';
    protected $description = 'Create an OAuth client';

    public function handle()
    {
        $id = $this->argument('id');
        $name = $this->argument('name');
        $redirect = $this->argument('redirect');
        $secret = $this->option('secret') ?: hash('sha256', $id . '-' . config('app.key'));

        try {
            // Check if client already exists
            $existing = OAuthClient::find($id);
            
            if ($existing) {
                $this->info("Cliente '{$id}' já existe:");
                $this->table(
                    ['Campo', 'Valor'],
                    [
                        ['ID', $existing->id],
                        ['Name', $existing->name],
                        ['Secret', $existing->secret],
                        ['Redirect URI', $existing->redirect],
                        ['Revoked', $existing->revoked ? 'Yes' : 'No'],
                    ]
                );
                return;
            }

            // Create new client
            $client = new OAuthClient();
            $client->id = $id;
            $client->identifier = $id; // Adicionar campo obrigatório
            $client->name = $name;
            $client->secret = $secret;
            $client->redirect = $redirect;
            $client->personal_access_client = false;
            $client->password_client = false;
            $client->revoked = false;

            if ($client->save()) {
                $this->info("✓ Cliente OAuth criado com sucesso!");
                $this->table(
                    ['Campo', 'Valor'],
                    [
                        ['ID', $client->id],
                        ['Name', $client->name],
                        ['Secret', $client->secret],
                        ['Redirect URI', $client->redirect],
                        ['Revoked', 'No'],
                    ]
                );

                $this->newLine();
                $this->info("Configuração Apache mod_auth_openidc:");
                $this->line("OIDCClientID {$client->id}");
                $this->line("OIDCClientSecret {$client->secret}");
                $this->line("OIDCRedirectURI {$client->redirect}");
            } else {
                $this->error("✗ Erro ao criar cliente OAuth");
            }

        } catch (\Exception $e) {
            $this->error("Erro: " . $e->getMessage());
            if ($this->option('verbose')) {
                $this->error("Stack trace: " . $e->getTraceAsString());
            }
        }
    }
}