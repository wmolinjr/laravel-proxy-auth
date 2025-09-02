<?php

namespace App\Notifications;

use App\Models\OAuthNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OAuthHealthCheckFailedNotification extends Notification implements ShouldQueue
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
        $consecutiveFailures = $data['consecutive_failures'] ?? 0;
        $healthCheckUrl = $data['health_check_url'] ?? 'N/A';

        return (new MailMessage)
            ->error()
            ->subject("[CRITICAL] OAuth Client Health Check Failed: {$client->name}")
            ->greeting('🚨 Critical Alert')
            ->line("The OAuth client **{$client->name}** has failed its health check {$consecutiveFailures} consecutive times.")
            ->line("**Client Details:**")
            ->line("• **Name:** {$client->name}")
            ->line("• **Environment:** " . ucfirst($client->environment))
            ->line("• **Health Check URL:** {$healthCheckUrl}")
            ->line("• **Consecutive Failures:** {$consecutiveFailures}")
            ->line("• **Last Successful Check:** " . ($data['last_success'] ? date('Y-m-d H:i:s', strtotime($data['last_success'])) : 'Never'))
            ->line('')
            ->line("**Immediate Action Required:**")
            ->line('• Check if the client application is running')
            ->line('• Verify the health check endpoint is responding')
            ->line('• Review application logs for errors')
            ->line('• Consider enabling maintenance mode if issues persist')
            ->action('View Client Details', url("/oauth-clients/{$client->id}"))
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
            'type' => 'health_check_failure',
            'consecutive_failures' => $this->oauthNotification->data['consecutive_failures'] ?? 0,
        ];
    }
}