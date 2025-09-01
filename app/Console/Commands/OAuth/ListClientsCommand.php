<?php

namespace App\Console\Commands\OAuth;

use App\Models\OAuth\OAuthClient;
use Illuminate\Console\Command;

class ListClientsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'oauth:clients:list 
                            {--active : Mostrar apenas clientes ativos}
                            {--revoked : Mostrar apenas clientes revogados}
                            {--format=table : Formato de saída (table, json)}';

    /**
     * The console command description.
     */
    protected $description = 'Listar todos os clientes OAuth2/OIDC';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $activeOnly = $this->option('active');
        $revokedOnly = $this->option('revoked');
        $format = $this->option('format');

        try {
            // Build query
            $query = OAuthClient::query();

            if ($activeOnly) {
                $query->where('revoked', false);
            } elseif ($revokedOnly) {
                $query->where('revoked', true);
            }

            $clients = $query->orderBy('created_at', 'desc')->get();

            if ($clients->isEmpty()) {
                $this->info('Nenhum cliente OAuth encontrado.');
                return self::SUCCESS;
            }

            // Display results
            if ($format === 'json') {
                $this->line($clients->toJson(JSON_PRETTY_PRINT));
                return self::SUCCESS;
            }

            // Table format
            $this->info("📋 Clientes OAuth ({$clients->count()})");
            $this->newLine();

            $this->table(
                ['ID', 'Nome', 'Status', 'Tipo', 'Redirect URIs', 'Criado em'],
                $clients->map(function (OAuthClient $client) {
                    return [
                        substr($client->id, 0, 8) . '...',
                        $client->name,
                        $client->revoked ? '❌ Revogado' : '✅ Ativo',
                        $client->secret ? 'Confidencial' : 'Público',
                        $this->truncateText($client->redirect, 40),
                        $client->created_at->format('d/m/Y H:i'),
                    ];
                })->toArray()
            );

            // Summary statistics
            $this->newLine();
            $activeCount = $clients->where('revoked', false)->count();
            $revokedCount = $clients->where('revoked', true)->count();
            $confidentialCount = $clients->where('secret', '!=', null)->count();
            $publicCount = $clients->where('secret', null)->count();

            $this->line('<comment>📊 Estatísticas:</comment>');
            $this->line("• Clientes ativos: {$activeCount}");
            $this->line("• Clientes revogados: {$revokedCount}");
            $this->line("• Clientes confidenciais: {$confidentialCount}");
            $this->line("• Clientes públicos: {$publicCount}");

            $this->newLine();
            $this->line('<comment>💡 Comandos úteis:</comment>');
            $this->line('• Criar cliente: php artisan oauth:client "Nome"');
            $this->line('• Revogar cliente: php artisan oauth:clients:revoke <client-id>');
            $this->line('• Ver detalhes: php artisan oauth:clients:show <client-id>');

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Erro ao listar clientes OAuth: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Truncate text for display
     */
    protected function truncateText(string $text, int $length = 50): string
    {
        if (strlen($text) <= $length) {
            return $text;
        }

        return substr($text, 0, $length - 3) . '...';
    }
}
