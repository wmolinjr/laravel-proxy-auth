<?php

namespace App\Console\Commands\OAuth;

use App\Models\OAuth\OAuthClient;
use App\Services\OAuth\OAuthClientService;
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
                            {--description= : Descrição do cliente (opcional)}
                            {--environment=production : Ambiente (production, staging, development)}
                            {--email= : Email de contato (opcional)}
                            {--version= : Versão do cliente (opcional)}
                            {--tags= : Tags separadas por vírgula (opcional)}
                            {--health-url= : URL para health check (opcional)}
                            {--health-interval=300 : Intervalo de health check em segundos}
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
        $description = $this->option('description');
        $environment = $this->option('environment');
        $email = $this->option('email');
        $version = $this->option('version');
        $tags = $this->option('tags');
        $healthUrl = $this->option('health-url');
        $healthInterval = $this->option('health-interval');
        $customSecret = $this->option('secret');
        $isPublic = $this->option('public');

        // Validar nome
        if (empty($name)) {
            $this->error('O nome do cliente é obrigatório.');
            return self::FAILURE;
        }

        // Validar ambiente
        if (!in_array($environment, ['production', 'staging', 'development'])) {
            $this->error('Ambiente deve ser: production, staging ou development.');
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

        // Processar tags
        $tagsArray = $tags ? array_map('trim', explode(',', $tags)) : [];

        try {
            $clientService = app(OAuthClientService::class);

            // Preparar dados do cliente
            $clientData = [
                'name' => $name,
                'description' => $description,
                'redirect_uris' => $redirectArray,
                'grants' => ['authorization_code', 'refresh_token'],
                'scopes' => ['read', 'openid', 'profile', 'email'],
                'is_confidential' => !$isPublic,
                'environment' => $environment,
                'contact_email' => $email,
                'version' => $version,
                'tags' => $tagsArray,
                'health_check_enabled' => !empty($healthUrl),
                'health_check_url' => $healthUrl,
                'health_check_interval' => (int)$healthInterval,
                'generate_secret' => !$isPublic,
            ];

            // Usar secret personalizado se fornecido
            if ($customSecret && !$isPublic) {
                $clientData['secret'] = $customSecret;
                $clientData['generate_secret'] = false;
            }

            // Criar cliente
            $client = $clientService->createClient($clientData);

            // Exibir resultado
            $this->info('✅ Cliente OAuth criado com sucesso!');
            $this->newLine();
            
            $this->line('<comment>📋 Informações do Cliente:</comment>');
            $clientInfo = [
                ['Nome', $client->name],
                ['Client ID', $client->id],
                ['Client Secret', $client->secret ? ($customSecret ?: '[Gerado automaticamente]') : '<Cliente Público>'],
                ['Redirect URIs', implode(', ', $client->redirect_uris)],
                ['Tipo', $client->is_confidential ? 'Confidencial' : 'Público'],
                ['Ambiente', ucfirst($client->environment)],
                ['Status', $client->is_active ? '✅ Ativo' : '❌ Inativo'],
            ];

            if ($client->description) {
                array_splice($clientInfo, 1, 0, [['Descrição', $client->description]]);
            }

            if ($client->contact_email) {
                $clientInfo[] = ['Email de Contato', $client->contact_email];
            }

            if ($client->version) {
                $clientInfo[] = ['Versão', $client->version];
            }

            if (!empty($client->tags)) {
                $clientInfo[] = ['Tags', implode(', ', $client->tags)];
            }

            if ($client->health_check_enabled) {
                $clientInfo[] = ['Health Check', "✅ Habilitado ({$client->health_check_url})"];
                $clientInfo[] = ['Intervalo Health Check', "{$client->health_check_interval}s"];
            }

            $this->table(['Campo', 'Valor'], $clientInfo);

            if ($client->is_confidential && $client->secret) {
                $this->newLine();
                $this->warn('⚠️  IMPORTANTE: Anote o Client Secret acima. Ele não será exibido novamente!');
                if (!$customSecret) {
                    $this->line("<info>Client Secret: {$client->secret}</info>");
                }
            }

            $this->newLine();
            $this->line('<comment>🌐 Endpoints OAuth2/OIDC:</comment>');
            $issuer = config('oauth.issuer', 'https://auth.wmj.com.br');
            $this->line("• Discovery: {$issuer}/.well-known/openid_configuration");
            $this->line("• Authorize: {$issuer}/oauth/authorize");
            $this->line("• Token: {$issuer}/oauth/token");
            $this->line("• UserInfo: {$issuer}/oauth/userinfo");

            $this->newLine();
            $this->line('<comment>🔗 Exemplo de URL de autorização:</comment>');
            $firstRedirectUri = $redirectArray[0];
            $exampleUrl = "{$issuer}/oauth/authorize?"
                . "response_type=code"
                . "&client_id={$client->id}"
                . "&redirect_uri=" . urlencode($firstRedirectUri)
                . "&scope=openid+profile+email"
                . "&state=random-state-value";
            
            $this->line($exampleUrl);

            $this->newLine();
            $this->line('<comment>💡 Comandos úteis:</comment>');
            $this->line("• Ver detalhes: <info>php artisan oauth:clients:show {$client->id}</info>");
            $this->line("• Listar clientes: <info>php artisan oauth:clients:list</info>");
            if ($client->health_check_enabled) {
                $this->line("• Testar health check: <info>php artisan oauth:clients:health-check {$client->id}</info>");
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Erro ao criar cliente OAuth: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}