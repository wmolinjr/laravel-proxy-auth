<?php

namespace App\Console\Commands\OAuth;

use App\Jobs\OAuth\OAuthCleanupJob;
use Illuminate\Console\Command;

class CleanupCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'oauth:cleanup 
                            {--types=* : Tipos de limpeza (tokens, events, usage)}
                            {--retention=90 : Dias de retenção}
                            {--sync : Executar sincronamente}
                            {--dry-run : Simular limpeza sem executar}';

    /**
     * The console command description.
     */
    protected $description = 'Limpar dados antigos do sistema OAuth';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $types = $this->option('types') ?: ['tokens', 'events', 'usage'];
        $retention = (int) $this->option('retention');
        $sync = $this->option('sync');
        $dryRun = $this->option('dry-run');

        // Validar tipos
        $validTypes = ['tokens', 'events', 'usage'];
        $invalidTypes = array_diff($types, $validTypes);
        
        if (!empty($invalidTypes)) {
            $this->error('Tipos inválidos: ' . implode(', ', $invalidTypes));
            $this->line('Tipos válidos: ' . implode(', ', $validTypes));
            return self::FAILURE;
        }

        // Validar retenção
        if ($retention < 1) {
            $this->error('Período de retenção deve ser pelo menos 1 dia');
            return self::FAILURE;
        }

        $this->info("🧹 Iniciando limpeza do sistema OAuth");
        $this->line("Tipos: " . implode(', ', $types));
        $this->line("Retenção: {$retention} dias");
        
        if ($dryRun) {
            $this->warn("🔍 MODO SIMULAÇÃO - Nenhum dado será removido");
            return $this->runDryRun($types, $retention);
        }

        if ($sync) {
            return $this->runSyncCleanup($types, $retention);
        } else {
            OAuthCleanupJob::dispatch($types, $retention);
            $this->info("✅ Job de limpeza enfileirado para execução em background");
            return self::SUCCESS;
        }
    }

    /**
     * Run dry run to show what would be cleaned
     */
    private function runDryRun(array $types, int $retention): int
    {
        $this->info("📋 Simulando limpeza...");
        $this->newLine();

        $stats = $this->getCleanupStats($retention);
        
        if (in_array('tokens', $types)) {
            $this->line("<comment>🎫 Tokens:</comment>");
            $this->line("• Tokens expirados: {$stats['expired_tokens']}");
            $this->line("• Códigos de autorização expirados: {$stats['expired_codes']}");
            $this->line("• Tokens antigos revogados: {$stats['old_revoked_tokens']}");
        }

        if (in_array('events', $types)) {
            $this->line("<comment>📝 Eventos:</comment>");
            $this->line("• Eventos antigos não-críticos: {$stats['old_events']}");
            $this->line("• Eventos críticos muito antigos: {$stats['old_critical_events']}");
        }

        if (in_array('usage', $types)) {
            $this->line("<comment>📊 Dados de Uso:</comment>");
            $this->line("• Registros de uso antigos: {$stats['old_usage_records']}");
        }

        $total = array_sum($stats);
        $this->newLine();
        $this->line("<comment>📈 Total de registros que seriam removidos: {$total}</comment>");

        if ($total > 0) {
            $this->warn("Para executar a limpeza, remova a flag --dry-run");
        } else {
            $this->info("✨ Nenhuma limpeza necessária");
        }

        return self::SUCCESS;
    }

    /**
     * Run synchronous cleanup
     */
    private function runSyncCleanup(array $types, int $retention): int
    {
        try {
            $this->info("🧹 Executando limpeza síncrona...");
            
            $job = new OAuthCleanupJob($types, $retention);
            $job->handle();
            
            $this->info("✅ Limpeza concluída com sucesso");
            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Erro durante a limpeza: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    /**
     * Get cleanup statistics without executing
     */
    private function getCleanupStats(int $retention): array
    {
        $now = now();
        $oldCutoff = $now->copy()->subDays($retention);
        $criticalCutoff = $now->copy()->subDays($retention * 2);
        $usageRetentionDays = max($retention * 4, 365);
        $usageCutoff = $now->copy()->subDays($usageRetentionDays);

        return [
            'expired_tokens' => \App\Models\OAuth\OAuthAccessToken::where('expires_at', '<', $now)
                                                                 ->where('revoked', true)
                                                                 ->count(),
            'expired_codes' => \App\Models\OAuth\OAuthAuthorizationCode::where('expires_at', '<', $now)
                                                                       ->count(),
            'old_revoked_tokens' => \App\Models\OAuth\OAuthAccessToken::where('revoked', true)
                                                                      ->where('updated_at', '<', $oldCutoff)
                                                                      ->count(),
            'old_events' => \App\Models\OAuth\OAuthClientEvent::where('occurred_at', '<', $oldCutoff)
                                                              ->where('severity', '!=', 'critical')
                                                              ->whereNotIn('event_type', ['security', 'error'])
                                                              ->count(),
            'old_critical_events' => \App\Models\OAuth\OAuthClientEvent::where('occurred_at', '<', $criticalCutoff)
                                                                       ->count(),
            'old_usage_records' => \App\Models\OAuth\OAuthClientUsage::where('date', '<', $usageCutoff->toDateString())
                                                                     ->count(),
        ];
    }

    /**
     * Show current system statistics
     */
    private function showSystemStats(): void
    {
        $this->newLine();
        $this->line("<comment>📊 Estatísticas Atuais do Sistema:</comment>");
        
        $totalClients = \App\Models\OAuth\OAuthClient::count();
        $activeClients = \App\Models\OAuth\OAuthClient::enabled()->count();
        $totalTokens = \App\Models\OAuth\OAuthAccessToken::count();
        $activeTokens = \App\Models\OAuth\OAuthAccessToken::where('expires_at', '>', now())
                                                         ->where('revoked', false)
                                                         ->count();
        $totalEvents = \App\Models\OAuth\OAuthClientEvent::count();
        $totalUsageRecords = \App\Models\OAuth\OAuthClientUsage::count();

        $this->line("• Clientes OAuth: {$totalClients} (Ativos: {$activeClients})");
        $this->line("• Access Tokens: {$totalTokens} (Válidos: {$activeTokens})");
        $this->line("• Eventos: {$totalEvents}");
        $this->line("• Registros de Uso: {$totalUsageRecords}");

        // Storage usage estimation
        $eventsSize = $totalEvents * 1024; // ~1KB per event estimate
        $usageSize = $totalUsageRecords * 512; // ~512B per usage record
        $totalSizeKB = ($eventsSize + $usageSize) / 1024;
        
        $this->line("• Uso estimado de armazenamento: " . number_format($totalSizeKB, 2) . " KB");
    }
}
