<?php

namespace App\Console\Commands\OAuth;

use App\Jobs\OAuth\OAuthUsageAggregationJob;
use Carbon\Carbon;
use Illuminate\Console\Command;

class UsageAggregationCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'oauth:usage:aggregate 
                            {--date= : Data específica para agregação (formato: Y-m-d)}
                            {--client= : ID do cliente específico}
                            {--days= : Número de dias anteriores para processar}
                            {--sync : Executar sincronamente}';

    /**
     * The console command description.
     */
    protected $description = 'Agregar dados de uso dos clientes OAuth';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dateOption = $this->option('date');
        $clientId = $this->option('client');
        $days = $this->option('days');
        $sync = $this->option('sync');

        try {
            if ($days) {
                return $this->aggregateMultipleDays((int)$days, $clientId, $sync);
            }

            if ($dateOption) {
                $date = Carbon::createFromFormat('Y-m-d', $dateOption);
                return $this->aggregateSpecificDate($date, $clientId, $sync);
            }

            // Default: aggregate yesterday's data
            $date = now()->yesterday();
            return $this->aggregateSpecificDate($date, $clientId, $sync);

        } catch (\Exception $e) {
            $this->error("Erro na agregação de dados: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    /**
     * Aggregate data for a specific date
     */
    private function aggregateSpecificDate(Carbon $date, ?string $clientId, bool $sync): int
    {
        $this->info("📊 Iniciando agregação de dados de uso");
        $this->line("Data: {$date->toDateString()}");
        
        if ($clientId) {
            $this->line("Cliente: {$clientId}");
        } else {
            $this->line("Clientes: Todos os clientes ativos");
        }

        if ($sync) {
            return $this->runSyncAggregation($date, $clientId);
        } else {
            OAuthUsageAggregationJob::dispatch($date, $clientId);
            $this->info("✅ Job de agregação enfileirado para execução em background");
            return self::SUCCESS;
        }
    }

    /**
     * Aggregate multiple days
     */
    private function aggregateMultipleDays(int $days, ?string $clientId, bool $sync): int
    {
        $this->info("📊 Agregando dados de uso para {$days} dias");
        
        $startDate = now()->subDays($days);
        $endDate = now()->yesterday();

        $this->line("Período: {$startDate->toDateString()} até {$endDate->toDateString()}");
        
        if ($clientId) {
            $this->line("Cliente: {$clientId}");
        }

        $dates = [];
        $currentDate = $startDate->copy();
        
        while ($currentDate->lte($endDate)) {
            $dates[] = $currentDate->copy();
            $currentDate->addDay();
        }

        $this->output->progressStart(count($dates));

        foreach ($dates as $date) {
            if ($sync) {
                $this->runSyncAggregation($date, $clientId, false);
            } else {
                OAuthUsageAggregationJob::dispatch($date, $clientId);
            }
            
            $this->output->progressAdvance();
        }

        $this->output->progressFinish();
        $this->newLine();

        if ($sync) {
            $this->info("✅ Agregação síncrona concluída para {$days} dias");
        } else {
            $this->info("✅ {$days} jobs de agregação enfileirados para execução em background");
        }

        return self::SUCCESS;
    }

    /**
     * Run synchronous aggregation
     */
    private function runSyncAggregation(Carbon $date, ?string $clientId, bool $showProgress = true): int
    {
        try {
            $job = new OAuthUsageAggregationJob($date, $clientId);
            
            if ($showProgress) {
                $this->line("Processando dados do dia {$date->toDateString()}...");
            }
            
            $job->handle();
            
            if ($showProgress) {
                $this->info("✅ Agregação concluída para {$date->toDateString()}");
            }
            
            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Erro na agregação para {$date->toDateString()}: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    /**
     * Show aggregation statistics
     */
    private function showAggregationStats(Carbon $date): void
    {
        $stats = \App\Models\OAuth\OAuthClientUsage::where('date', $date->toDateString())->get();
        
        if ($stats->isEmpty()) {
            $this->line("Nenhum dado agregado encontrado para {$date->toDateString()}");
            return;
        }

        $this->newLine();
        $this->line("<comment>📈 Estatísticas de Agregação - {$date->toDateString()}:</comment>");
        
        $totalAuthRequests = $stats->sum('authorization_requests');
        $totalTokenRequests = $stats->sum('token_requests');
        $totalApiCalls = $stats->sum('api_calls');
        $totalUsers = $stats->sum('unique_users');
        $totalErrors = $stats->sum('error_count');
        
        $this->line("• Clientes processados: {$stats->count()}");
        $this->line("• Total de autorizações: {$totalAuthRequests}");
        $this->line("• Total de tokens: {$totalTokenRequests}");
        $this->line("• Total de chamadas API: {$totalApiCalls}");
        $this->line("• Total de usuários únicos: {$totalUsers}");
        $this->line("• Total de erros: {$totalErrors}");
        
        // Top clients by activity
        $topClients = $stats->sortByDesc('api_calls')->take(3);
        
        if ($topClients->isNotEmpty()) {
            $this->newLine();
            $this->line("<comment>🏆 Top 3 Clientes por Atividade:</comment>");
            
            foreach ($topClients as $index => $usage) {
                $client = $usage->oauthClient;
                $position = $index + 1;
                $this->line("{$position}. {$client->name}: {$usage->api_calls} chamadas API");
            }
        }
    }
}
