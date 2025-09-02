<?php

namespace App\Services\OAuth;

use App\Models\OAuth\OAuthClient;
use App\Models\OAuthAlertRule;
use App\Models\OAuthNotification;
use App\Models\User;
use App\Notifications\OAuthHealthCheckFailedNotification;
use App\Notifications\OAuthHighErrorRateNotification;
use App\Notifications\OAuthMaintenanceModeNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

class OAuthNotificationService
{
    public function evaluateAndTriggerAlerts(OAuthClient $client, string $triggerType, array $data): void
    {
        $rules = OAuthAlertRule::active()
            ->byTriggerType($triggerType)
            ->get();

        foreach ($rules as $rule) {
            if ($rule->canTrigger() && $rule->evaluateConditions($data)) {
                $this->triggerAlert($client, $rule, $data);
            }
        }
    }

    public function triggerAlert(OAuthClient $client, OAuthAlertRule $rule, array $data): void
    {
        try {
            // Create notification record
            $notification = $this->createNotificationFromRule($client, $rule, $data);
            
            // Send notifications through configured channels
            $this->sendNotification($notification, $rule);
            
            // Mark rule as triggered
            $rule->markTriggered();
            
            Log::info('OAuth alert triggered', [
                'client_id' => $client->id,
                'client_name' => $client->name,
                'rule_id' => $rule->id,
                'rule_name' => $rule->name,
                'trigger_type' => $rule->trigger_type,
                'data' => $data,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to trigger OAuth alert', [
                'client_id' => $client->id,
                'rule_id' => $rule->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function createNotificationFromRule(OAuthClient $client, OAuthAlertRule $rule, array $data): OAuthNotification
    {
        return match ($rule->trigger_type) {
            'health_check_failure' => OAuthNotification::createHealthCheckAlert(
                $client,
                $data['consecutive_failures'] ?? 0,
                $rule
            ),
            'high_error_rate' => OAuthNotification::createHighErrorRateAlert(
                $client,
                $data['error_rate_percent'] ?? 0,
                $rule
            ),
            'maintenance_mode' => OAuthNotification::createMaintenanceModeAlert(
                $client,
                $data['entering'] ?? true,
                $data['reason'] ?? null,
                $rule
            ),
            default => $this->createGenericNotification($client, $rule, $data),
        };
    }

    private function createGenericNotification(OAuthClient $client, OAuthAlertRule $rule, array $data): OAuthNotification
    {
        return OAuthNotification::create([
            'oauth_client_id' => $client->id,
            'alert_rule_id' => $rule->id,
            'type' => 'alert',
            'title' => $rule->name,
            'message' => $rule->description ?? "Alert triggered for client '{$client->name}'",
            'data' => $data,
            'recipients' => $rule->recipients,
            'status' => 'pending',
        ]);
    }

    private function sendNotification(OAuthNotification $notification, OAuthAlertRule $rule): void
    {
        $channels = $rule->notification_channels ?? [];
        $recipients = $rule->recipients ?? [];
        $sentChannels = [];

        foreach ($channels as $channel) {
            try {
                switch ($channel) {
                    case 'email':
                        $this->sendEmailNotification($notification, $recipients);
                        $sentChannels[] = 'email';
                        break;
                        
                    case 'slack':
                        $this->sendSlackNotification($notification, $recipients);
                        $sentChannels[] = 'slack';
                        break;
                        
                    case 'in_app':
                        $this->sendInAppNotification($notification, $recipients);
                        $sentChannels[] = 'in_app';
                        break;
                        
                    case 'webhook':
                        $this->sendWebhookNotification($notification, $recipients);
                        $sentChannels[] = 'webhook';
                        break;
                        
                    default:
                        Log::warning("Unknown notification channel: {$channel}");
                }
            } catch (\Exception $e) {
                Log::error("Failed to send notification via {$channel}", [
                    'notification_id' => $notification->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (!empty($sentChannels)) {
            $notification->markAsSent($sentChannels);
        } else {
            $notification->markAsFailed();
        }
    }

    private function sendEmailNotification(OAuthNotification $notification, array $recipients): void
    {
        $emailRecipients = $this->getEmailRecipients($recipients);
        
        if (empty($emailRecipients)) {
            return;
        }

        $notificationClass = match ($notification->alertRule?->trigger_type) {
            'health_check_failure' => OAuthHealthCheckFailedNotification::class,
            'high_error_rate' => OAuthHighErrorRateNotification::class,
            'maintenance_mode' => OAuthMaintenanceModeNotification::class,
            default => OAuthHealthCheckFailedNotification::class, // Generic fallback
        };

        foreach ($emailRecipients as $email) {
            try {
                Notification::route('mail', $email)->notify(new $notificationClass($notification));
            } catch (\Exception $e) {
                Log::error("Failed to send email notification to {$email}", [
                    'notification_id' => $notification->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function sendSlackNotification(OAuthNotification $notification, array $recipients): void
    {
        // Implementation depends on Slack integration setup
        // This is a placeholder for future Slack integration
        Log::info('Slack notification would be sent', [
            'notification_id' => $notification->id,
            'title' => $notification->title,
            'message' => $notification->message,
        ]);
    }

    private function sendInAppNotification(OAuthNotification $notification, array $recipients): void
    {
        $users = $this->getUserRecipients($recipients);
        
        foreach ($users as $user) {
            $user->notifications()->create([
                'id' => \Illuminate\Support\Str::uuid(),
                'type' => 'App\\Notifications\\OAuthAlertNotification',
                'data' => [
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'type' => $notification->type,
                    'client_name' => $notification->oauthClient?->name,
                    'notification_id' => $notification->id,
                ],
                'read_at' => null,
            ]);
        }
    }

    private function sendWebhookNotification(OAuthNotification $notification, array $recipients): void
    {
        // Implementation for webhook notifications
        // This would send POST requests to configured webhook URLs
        Log::info('Webhook notification would be sent', [
            'notification_id' => $notification->id,
            'payload' => [
                'title' => $notification->title,
                'message' => $notification->message,
                'type' => $notification->type,
                'client' => $notification->oauthClient?->name,
                'data' => $notification->data,
            ],
        ]);
    }

    private function getEmailRecipients(array $recipients): array
    {
        $emails = [];
        
        foreach ($recipients as $recipient) {
            if (is_string($recipient) && filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $recipient;
            } elseif (is_numeric($recipient)) {
                $user = User::find($recipient);
                if ($user && $user->email) {
                    $emails[] = $user->email;
                }
            }
        }
        
        return array_unique($emails);
    }

    private function getUserRecipients(array $recipients): array
    {
        $users = [];
        
        foreach ($recipients as $recipient) {
            if (is_numeric($recipient)) {
                $user = User::find($recipient);
                if ($user) {
                    $users[] = $user;
                }
            }
        }
        
        return $users;
    }

    // Health check specific alerts
    public function checkHealthCheckFailures(OAuthClient $client): void
    {
        if (!$client->health_check_enabled) {
            return;
        }

        $this->evaluateAndTriggerAlerts($client, 'health_check_failure', [
            'consecutive_failures' => $client->health_check_failures,
            'health_status' => $client->health_status,
            'last_check' => $client->last_health_check_at?->toISOString(),
        ]);
    }

    // Error rate specific alerts  
    public function checkErrorRate(OAuthClient $client, float $errorRate): void
    {
        $this->evaluateAndTriggerAlerts($client, 'high_error_rate', [
            'error_rate_percent' => $errorRate,
            'measurement_time' => now()->toISOString(),
        ]);
    }

    // Response time alerts
    public function checkResponseTime(OAuthClient $client, float $avgResponseTime): void
    {
        $this->evaluateAndTriggerAlerts($client, 'response_time_threshold', [
            'avg_response_time' => $avgResponseTime,
            'measurement_time' => now()->toISOString(),
        ]);
    }

    // Maintenance mode alerts
    public function notifyMaintenanceMode(OAuthClient $client, bool $entering, ?string $reason = null): void
    {
        $this->evaluateAndTriggerAlerts($client, 'maintenance_mode', [
            'entering' => $entering,
            'reason' => $reason,
            'timestamp' => now()->toISOString(),
        ]);
    }

    // Get unacknowledged notifications for dashboard
    public function getUnacknowledgedNotifications(int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return OAuthNotification::with(['oauthClient', 'alertRule'])
            ->unacknowledged()
            ->latest()
            ->limit($limit)
            ->get();
    }

    // Get notification statistics
    public function getNotificationStats(): array
    {
        return [
            'total_today' => OAuthNotification::whereDate('created_at', today())->count(),
            'unacknowledged' => OAuthNotification::unacknowledged()->count(),
            'critical_unacknowledged' => OAuthNotification::critical()->unacknowledged()->count(),
            'by_type' => OAuthNotification::selectRaw('type, COUNT(*) as count')
                ->whereDate('created_at', '>=', now()->subDays(7))
                ->groupBy('type')
                ->pluck('count', 'type')
                ->toArray(),
        ];
    }
}