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
                            {--environment= : Filtrar por ambiente (production, staging, development)}
                            {--health= : Filtrar por status de saúde (healthy, unhealthy, error)}
                            {--maintenance : Mostrar apenas clientes em manutenção}
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
                $query->enabled();
            } elseif ($revokedOnly) {
                $query->where('revoked', true);
            }

            // Filter by environment
            if ($this->option('environment')) {
                $query->where('environment', $this->option('environment'));
            }

            // Filter by health status
            if ($this->option('health')) {
                $query->healthStatus($this->option('health'));
            }

            // Filter by maintenance mode
            if ($this->option('maintenance')) {
                $query->inMaintenance();
            }

            $clients = $query->with(['creator', 'updater'])
                            ->orderBy('created_at', 'desc')
                            ->get();

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
                ['ID', 'Nome', 'Ambiente', 'Status', 'Saúde', 'Tipo', 'Health Check', 'Criado em'],
                $clients->map(function (OAuthClient $client) {
                    return [
                        substr($client->id, 0, 8) . '...',
                        $client->name,
                        ucfirst($client->environment),
                        $this->getStatusDisplay($client),
                        $this->getHealthDisplay($client),
                        $client->is_confidential ? 'Confidencial' : 'Público',
                        $client->health_check_enabled ? 
                            ($client->needsHealthCheck() ? '⚠️  Pendente' : '✅ OK') : 
                            '➖ Desabilitado',
                        $client->created_at->format('d/m/Y H:i'),
                    ];
                })->toArray()
            );

            // Summary statistics
            $this->newLine();
            $enabledCount = $clients->where('is_active', true)->where('revoked', false)->count();
            $revokedCount = $clients->where('revoked', true)->count();
            $maintenanceCount = $clients->where('maintenance_mode', true)->count();
            $confidentialCount = $clients->where('is_confidential', true)->count();
            $publicCount = $clients->where('is_confidential', false)->count();
            $healthyCount = $clients->where('health_status', 'healthy')->count();
            $unhealthyCount = $clients->whereIn('health_status', ['unhealthy', 'error'])->count();
            $productionCount = $clients->where('environment', 'production')->count();
            $stagingCount = $clients->where('environment', 'staging')->count();
            $developmentCount = $clients->where('environment', 'development')->count();

            $this->line('<comment>📊 Estatísticas:</comment>');
            $this->line("• Clientes habilitados: {$enabledCount}");
            $this->line("• Clientes revogados: {$revokedCount}");
            $this->line("• Em manutenção: {$maintenanceCount}");
            $this->line("• Confidenciais: {$confidentialCount} | Públicos: {$publicCount}");
            $this->line("• Saudáveis: {$healthyCount} | Com problemas: {$unhealthyCount}");
            $this->line("• Production: {$productionCount} | Staging: {$stagingCount} | Development: {$developmentCount}");

            $this->newLine();
            $this->line('<comment>💡 Comandos úteis:</comment>');
            $this->line('• Criar cliente: <info>php artisan oauth:client "Nome" --redirect="https://app.com/callback"</info>');
            $this->line('• Ver detalhes: <info>php artisan oauth:clients:show <client-id></info>');
            $this->line('• Health check: <info>php artisan oauth:clients:health-check <client-id></info>');
            $this->line('• Filtrar por ambiente: <info>php artisan oauth:clients:list --environment=production</info>');
            $this->line('• Filtrar por saúde: <info>php artisan oauth:clients:list --health=healthy</info>');

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

    /**
     * Get status display with icons
     */
    protected function getStatusDisplay(OAuthClient $client): string
    {
        if ($client->revoked) {
            return '❌ Revogado';
        }

        if ($client->maintenance_mode) {
            return '🔧 Manutenção';
        }

        if (!$client->is_active) {
            return '⏸️  Inativo';
        }

        return '✅ Ativo';
    }

    /**
     * Get health display with icons
     */
    protected function getHealthDisplay(OAuthClient $client): string
    {
        if (!$client->health_check_enabled) {
            return '➖ N/A';
        }

        return match ($client->health_status) {
            'healthy' => '✅ Saudável',
            'unhealthy' => '⚠️  Com problemas',
            'error' => '❌ Erro',
            default => '❓ Desconhecido'
        };
    }
}
