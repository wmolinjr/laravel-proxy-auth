<?php

namespace App\Console\Commands\OAuth;

use App\Models\OAuth\OAuthClient;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateClientCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'oauth:client 
                            {name : O nome do cliente OAuth}
                            {--redirect= : URIs de redirecionamento (separadas por vírgula)}
                            {--secret= : Client secret personalizado (opcional)}
                            {--public : Criar cliente público (sem secret)}';

    /**
     * The console command description.
     */
    protected $description = 'Criar um novo cliente OAuth2/OIDC';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = $this->argument('name');
        $redirectUris = $this->option('redirect');
        $customSecret = $this->option('secret');
        $isPublic = $this->option('public');

        // Validar nome
        if (empty($name)) {
            $this->error('O nome do cliente é obrigatório.');
            return self::FAILURE;
        }

        // Solicitar URIs de redirecionamento se não fornecidas
        if (!$redirectUris) {
            $redirectUris = $this->ask('URIs de redirecionamento (separadas por vírgula)');
            
            if (!$redirectUris) {
                $this->error('Pelo menos uma URI de redirecionamento é obrigatória.');
                return self::FAILURE;
            }
        }

        // Validar URIs de redirecionamento
        $redirectArray = array_map('trim', explode(',', $redirectUris));
        foreach ($redirectArray as $uri) {
            if (!filter_var($uri, FILTER_VALIDATE_URL)) {
                $this->error("URI inválida: {$uri}");
                return self::FAILURE;
            }
        }

        try {
            // Gerar dados do cliente
            $clientId = Str::uuid()->toString();
            $clientSecret = $isPublic ? null : ($customSecret ?: Str::random(40));

            // Criar cliente
            $client = OAuthClient::create([
                'id' => $clientId,
                'name' => $name,
                'secret' => $clientSecret,
                'redirect' => $redirectUris,
                'personal_access_client' => false,
                'password_client' => false,
                'revoked' => false,
            ]);

            // Exibir resultado
            $this->info('Cliente OAuth criado com sucesso!');
            $this->newLine();
            
            $this->line('<comment>Informações do Cliente:</comment>');
            $this->table(
                ['Campo', 'Valor'],
                [
                    ['Nome', $client->name],
                    ['Client ID', $client->id],
                    ['Client Secret', $clientSecret ?: '<Cliente Público>'],
                    ['Redirect URIs', $client->redirect],
                    ['Tipo', $isPublic ? 'Público' : 'Confidencial'],
                    ['Status', 'Ativo'],
                ]
            );

            if (!$isPublic && $clientSecret) {
                $this->newLine();
                $this->warn('⚠️  IMPORTANTE: Anote o Client Secret acima. Ele não será exibido novamente!');
            }

            $this->newLine();
            $this->line('<comment>Endpoints OAuth2/OIDC:</comment>');
            $issuer = config('oauth.issuer');
            $this->line("• Discovery: {$issuer}/.well-known/openid_configuration");
            $this->line("• Authorize: {$issuer}/oauth/authorize");
            $this->line("• Token: {$issuer}/oauth/token");
            $this->line("• UserInfo: {$issuer}/oauth/userinfo");

            $this->newLine();
            $this->line('<comment>Exemplo de URL de autorização:</comment>');
            $firstRedirectUri = $redirectArray[0];
            $exampleUrl = "{$issuer}/oauth/authorize?"
                . "response_type=code"
                . "&client_id={$clientId}"
                . "&redirect_uri=" . urlencode($firstRedirectUri)
                . "&scope=openid+profile+email"
                . "&state=random-state-value";
            
            $this->line($exampleUrl);

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Erro ao criar cliente OAuth: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}