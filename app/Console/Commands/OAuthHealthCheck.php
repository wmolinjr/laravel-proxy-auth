<?php

namespace App\Console\Commands;

use App\Services\OAuthAlertService;
use Illuminate\Console\Command;

class OAuthHealthCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'oauth:health-check {--test : Send test alert}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check OAuth metrics and trigger alerts for anomalies';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 Checking OAuth metrics...');

        if ($this->option('test')) {
            $this->info('🧪 Sending test alert...');
            $result = OAuthAlertService::testAlerts();
            $this->info('✅ Test alert sent: ' . $result['status']);
            return self::SUCCESS;
        }

        try {
            // Check metrics and get alerts
            $alerts = OAuthAlertService::checkMetrics();
            
            // Get health status
            $health = OAuthAlertService::getHealthStatus();
            
            $this->info("📊 System Status: " . strtoupper($health['status']));
            $this->info("📈 Requests (5min): " . $health['metrics']['requests_5min']);
            $this->info("⏱️  Avg Response Time: " . $health['metrics']['avg_response_time'] . "ms");
            $this->info("❌ Error Rate: " . $health['metrics']['error_rate'] . "%");
            
            if (!empty($health['issues'])) {
                $this->warn("⚠️  Issues detected:");
                foreach ($health['issues'] as $issue) {
                    $this->warn("  - " . $issue);
                }
            }

            if (!empty($alerts)) {
                $this->warn("🚨 " . count($alerts) . " alerts triggered:");
                
                $critical = array_filter($alerts, fn($a) => $a['severity'] === 'critical');
                $warnings = array_filter($alerts, fn($a) => $a['severity'] === 'warning');
                
                if (!empty($critical)) {
                    $this->error("  Critical (" . count($critical) . "):");
                    foreach ($critical as $alert) {
                        $this->error("    - " . $alert['message']);
                    }
                }
                
                if (!empty($warnings)) {
                    $this->warn("  Warnings (" . count($warnings) . "):");
                    foreach ($warnings as $alert) {
                        $this->warn("    - " . $alert['message']);
                    }
                }
            } else {
                $this->info("✅ No alerts - system operating normally");
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Health check failed: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}
