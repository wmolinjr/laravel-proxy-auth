<?php

use App\Jobs\OAuth\OAuthHealthCheckJob;
use App\Jobs\OAuth\OAuthUsageAggregationJob;
use App\Jobs\OAuth\OAuthCleanupJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| OAuth Monitoring & Maintenance Schedule
|--------------------------------------------------------------------------
|
| Agendamento automático para monitoramento contínuo e manutenção
| do sistema OAuth. Configura jobs para health checks, agregação
| de dados de uso e limpeza de registros antigos.
|
*/

// Health Checks - Executar a cada 5 minutos
Schedule::job(new OAuthHealthCheckJob())
        ->everyFiveMinutes()
        ->name('oauth-health-check')
        ->description('Monitoramento automático de saúde dos clientes OAuth')
        ->onOneServer() // Executar apenas em um servidor se houver múltiplos
        ->withoutOverlapping(10); // Evitar sobreposição por até 10 minutos

// Agregação de Dados de Uso - Executar diariamente às 02:00
Schedule::job(new OAuthUsageAggregationJob(now()->yesterday()))
        ->dailyAt('02:00')
        ->name('oauth-usage-aggregation')
        ->description('Agregação diária de dados de uso dos clientes OAuth')
        ->timezone('America/Sao_Paulo')
        ->onOneServer()
        ->withoutOverlapping(60); // Evitar sobreposição por até 1 hora

// Limpeza Semanal - Executar aos domingos às 03:00
Schedule::job(new OAuthCleanupJob(['tokens', 'events', 'usage'], 90))
        ->weeklyOn(0, '03:00') // Domingo às 03:00
        ->name('oauth-weekly-cleanup')
        ->description('Limpeza semanal de dados antigos do sistema OAuth')
        ->timezone('America/Sao_Paulo')
        ->onOneServer()
        ->withoutOverlapping(120); // Evitar sobreposição por até 2 horas

// Limpeza Mensal Intensiva - Primeiro dia do mês às 04:00
Schedule::job(new OAuthCleanupJob(['tokens', 'events', 'usage'], 60))
        ->monthlyOn(1, '04:00') // Primeiro dia do mês às 04:00
        ->name('oauth-monthly-intensive-cleanup')
        ->description('Limpeza mensal intensiva (60 dias de retenção)')
        ->timezone('America/Sao_Paulo')
        ->onOneServer()
        ->withoutOverlapping(240); // Evitar sobreposição por até 4 horas

// Health Check Forçado Diário - Executar às 06:00
Schedule::job(new OAuthHealthCheckJob(null, true))
        ->dailyAt('06:00')
        ->name('oauth-daily-force-health-check')
        ->description('Health check forçado diário de todos os clientes')
        ->timezone('America/Sao_Paulo')
        ->onOneServer()
        ->withoutOverlapping(30);

// Comandos auxiliares usando Schedule::command()

// Comando para verificar clientes que precisam de atenção
Schedule::command('oauth:clients:list --maintenance --format=json')
        ->hourly()
        ->name('oauth-maintenance-check')
        ->description('Verificação horária de clientes em manutenção')
        ->onOneServer();

// Agregação de dados históricos (últimos 7 dias) - Executar semanalmente
Schedule::command('oauth:usage:aggregate --days=7')
        ->weekly()
        ->saturdays()
        ->at('01:00')
        ->name('oauth-weekly-historical-aggregation')
        ->description('Reagregação semanal de dados históricos')
        ->timezone('America/Sao_Paulo')
        ->onOneServer();

/*
|--------------------------------------------------------------------------
| Monitoramento e Logging
|--------------------------------------------------------------------------
*/

// Log de execução dos jobs agendados
Schedule::command('schedule:list')
        ->dailyAt('12:00')
        ->name('oauth-schedule-monitoring')
        ->description('Log diário do status dos jobs agendados')
        ->onOneServer();

/*
|--------------------------------------------------------------------------
| Comandos de Exemplo para Execução Manual
|--------------------------------------------------------------------------
|
| Para executar jobs manualmente (útil para desenvolvimento e testes):
|
| Health Check:
| php artisan oauth:clients:health-check --sync --show-results
| php artisan oauth:clients:health-check {client-id} --sync
| php artisan oauth:clients:health-check --force --sync
|
| Agregação de Uso:
| php artisan oauth:usage:aggregate --sync
| php artisan oauth:usage:aggregate --date=2024-01-15 --sync
| php artisan oauth:usage:aggregate --days=7 --sync
|
| Limpeza:
| php artisan oauth:cleanup --dry-run
| php artisan oauth:cleanup --types=tokens,events --retention=30 --sync
| php artisan oauth:cleanup --sync
|
*/
