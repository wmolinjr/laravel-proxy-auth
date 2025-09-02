<?php

namespace App\Console\Commands\OAuth;

use App\Jobs\OAuth\OAuthHealthCheckJob;
use App\Models\OAuth\OAuthClient;
use Illuminate\Console\Command;

class HealthCheckCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'oauth:clients:health-check 
                            {client? : ID do cliente específico para verificar}
                            {--force : Forçar verificação de todos os clientes}
                            {--sync : Executar sincronamente ao invés de enfileirar}
                            {--show-results : Mostrar resultados detalhados}';

    /**
     * The console command description.
     */
    protected $description = 'Executar health check em clientes OAuth';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $clientId = $this->argument('client');
        $force = $this->option('force');
        $sync = $this->option('sync');
        $showResults = $this->option('show-results');

        if ($clientId) {
            return $this->checkSpecificClient($clientId, $sync, $showResults);
        }

        if ($force) {
            return $this->forceCheckAllClients($sync, $showResults);
        }

        return $this->checkClientsNeedingHealthCheck($sync, $showResults);
    }

    /**
     * Check a specific client
     */
    private function checkSpecificClient(string $clientId, bool $sync, bool $showResults): int
    {
        $client = OAuthClient::find($clientId);

        if (!$client) {
            $this->error("Cliente não encontrado: {$clientId}");
            return self::FAILURE;
        }

        if (!$client->health_check_enabled) {
            $this->error("Health check não está habilitado para o cliente: {$client->name}");
            return self::FAILURE;
        }

        $this->info("🔍 Verificando saúde do cliente: {$client->name}");

        if ($sync) {
            return $this->runSyncHealthCheck($client, $showResults);
        } else {
            OAuthHealthCheckJob::dispatch($clientId);
            $this->info("✅ Health check enfileirado para execução em background");
            return self::SUCCESS;
        }
    }

    /**
     * Force check all clients with health check enabled
     */
    private function forceCheckAllClients(bool $sync, bool $showResults): int
    {
        $clients = OAuthClient::enabled()
                             ->where('health_check_enabled', true)
                             ->whereNotNull('health_check_url')
                             ->get();

        if ($clients->isEmpty()) {
            $this->info('🤷 Nenhum cliente com health check habilitado encontrado');
            return self::SUCCESS;
        }

        $this->info("🔍 Forçando health check em {$clients->count()} clientes");

        if ($sync) {
            return $this->runSyncHealthCheckBatch($clients, $showResults);
        } else {
            OAuthHealthCheckJob::dispatch(null, true);
            $this->info("✅ Health check em lote enfileirado para execução em background");
            return self::SUCCESS;
        }
    }

    /**
     * Check only clients that need health check
     */
    private function checkClientsNeedingHealthCheck(bool $sync, bool $showResults): int
    {
        $clientService = app(\App\Services\OAuth\OAuthClientService::class);
        $clients = $clientService->getClientsNeedingHealthCheck();

        if ($clients->isEmpty()) {
            $this->info('✅ Todos os clientes estão com health check atualizado');
            return self::SUCCESS;
        }

        $this->info("🔍 Verificando {$clients->count()} clientes que precisam de health check");

        if ($sync) {
            return $this->runSyncHealthCheckBatch($clients, $showResults);
        } else {
            OAuthHealthCheckJob::dispatch();
            $this->info("✅ Health check enfileirado para execução em background");
            return self::SUCCESS;
        }
    }

    /**
     * Run synchronous health check for a single client
     */
    private function runSyncHealthCheck(OAuthClient $client, bool $showResults): int
    {
        $clientService = app(\App\Services\OAuth\OAuthClientService::class);
        
        $this->line("Verificando: {$client->name} ({$client->health_check_url})");
        
        $result = $clientService->performHealthCheck($client);
        
        if ($showResults) {
            $this->displayHealthCheckResult($client, $result);
        }

        switch ($result['status']) {
            case 'healthy':
                $this->info("✅ Cliente saudável");
                return self::SUCCESS;
            case 'unhealthy':
                $this->warn("⚠️  Cliente com problemas: {$result['message']}");
                return self::SUCCESS; // Not a failure, just unhealthy
            case 'error':
                $this->error("❌ Erro no health check: {$result['message']}");
                return self::SUCCESS; // Not a command failure
            case 'disabled':
                $this->info("➖ Health check desabilitado");
                return self::SUCCESS;
            default:
                $this->error("❓ Status desconhecido: {$result['status']}");
                return self::FAILURE;
        }
    }

    /**
     * Run synchronous health check for multiple clients
     */
    private function runSyncHealthCheckBatch($clients, bool $showResults): int
    {
        $clientService = app(\App\Services\OAuth\OAuthClientService::class);
        $results = [];
        
        $this->output->progressStart($clients->count());

        foreach ($clients as $client) {
            $result = $clientService->performHealthCheck($client);
            $results[] = ['client' => $client, 'result' => $result];
            
            $this->output->progressAdvance();
            
            // Small delay between checks
            usleep(500000); // 0.5 second
        }

        $this->output->progressFinish();
        $this->newLine();

        if ($showResults) {
            $this->displayBatchResults($results);
        }

        // Summary
        $healthy = collect($results)->where('result.status', 'healthy')->count();
        $unhealthy = collect($results)->where('result.status', 'unhealthy')->count();
        $errors = collect($results)->where('result.status', 'error')->count();

        $this->newLine();
        $this->line('<comment>📊 Resultados:</comment>');
        $this->line("• Saudáveis: {$healthy}");
        $this->line("• Com problemas: {$unhealthy}");
        $this->line("• Erros: {$errors}");

        return self::SUCCESS;
    }

    /**
     * Display health check result for a single client
     */
    private function displayHealthCheckResult(OAuthClient $client, array $result): void
    {
        $this->newLine();
        $this->line("<comment>🏥 Resultado do Health Check:</comment>");
        $this->line("Cliente: {$client->name}");
        $this->line("URL: {$client->health_check_url}");
        $this->line("Status: {$result['status']}");
        $this->line("Mensagem: {$result['message']}");
        
        if (isset($result['response_time_ms'])) {
            $this->line("Tempo de resposta: {$result['response_time_ms']}ms");
        }
        
        if (isset($result['status_code'])) {
            $this->line("Código HTTP: {$result['status_code']}");
        }
    }

    /**
     * Display batch results
     */
    private function displayBatchResults(array $results): void
    {
        $this->newLine();
        $this->table(
            ['Cliente', 'URL', 'Status', 'Tempo (ms)', 'Código HTTP'],
            collect($results)->map(function ($item) {
                $client = $item['client'];
                $result = $item['result'];
                
                return [
                    $client->name,
                    $this->truncateUrl($client->health_check_url),
                    $this->getStatusIcon($result['status']),
                    $result['response_time_ms'] ?? 'N/A',
                    $result['status_code'] ?? 'N/A',
                ];
            })->toArray()
        );
    }

    /**
     * Get status icon
     */
    private function getStatusIcon(string $status): string
    {
        return match ($status) {
            'healthy' => '✅ Saudável',
            'unhealthy' => '⚠️  Problemas',
            'error' => '❌ Erro',
            'disabled' => '➖ Desabilitado',
            default => '❓ Desconhecido'
        };
    }

    /**
     * Truncate URL for display
     */
    private function truncateUrl(string $url, int $length = 40): string
    {
        if (strlen($url) <= $length) {
            return $url;
        }

        return substr($url, 0, $length - 3) . '...';
    }
}
