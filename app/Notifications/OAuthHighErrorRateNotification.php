<?php

namespace App\Notifications;

use App\Models\OAuthNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OAuthHighErrorRateNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private OAuthNotification $oauthNotification)
    {
        $this->onQueue('notifications');
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $client = $this->oauthNotification->oauthClient;
        $data = $this->oauthNotification->data;
        $errorRate = $data['error_rate_percent'] ?? 0;
        $threshold = $data['threshold'] ?? 10;
        $period = $data['measurement_period'] ?? '15 minutes';

        $severity = $errorRate >= 50 ? 'CRITICAL' : ($errorRate >= 25 ? 'HIGH' : 'WARNING');
        
        return (new MailMessage)
            ->error()
            ->subject("[{$severity}] High Error Rate Alert: {$client->name}")
            ->greeting("⚠️ {$severity} Alert")
            ->line("The OAuth client **{$client->name}** is experiencing a high error rate.")
            ->line("**Alert Details:**")
            ->line("• **Current Error Rate:** {$errorRate}%")
            ->line("• **Threshold:** {$threshold}%")
            ->line("• **Measurement Period:** {$period}")
            ->line("• **Environment:** " . ucfirst($client->environment))
            ->line('')
            ->line("**Recommended Actions:**")
            ->line('• Review recent application logs for error patterns')
            ->line('• Check OAuth client configuration and credentials')
            ->line('• Verify network connectivity and dependencies')
            ->line('• Monitor token validation and authorization flows')
            ->line('• Consider temporarily reducing traffic if needed')
            ->action('View Client Analytics', url("/oauth-clients/{$client->id}"))
            ->line('')
            ->line("**Error Rate Thresholds:**")
            ->line("• **Warning:** 10-25% errors")
            ->line("• **High:** 25-50% errors") 
            ->line("• **Critical:** 50%+ errors")
            ->line('')
            ->line('This alert was generated automatically by the OAuth monitoring system.')
            ->salutation('OAuth Monitoring System');
    }

    public function toArray($notifiable): array
    {
        return [
            'notification_id' => $this->oauthNotification->id,
            'client_id' => $this->oauthNotification->oauth_client_id,
            'client_name' => $this->oauthNotification->oauthClient?->name,
            'type' => 'high_error_rate',
            'error_rate_percent' => $this->oauthNotification->data['error_rate_percent'] ?? 0,
            'threshold' => $this->oauthNotification->data['threshold'] ?? 10,
        ];
    }
}