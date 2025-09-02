<?php

namespace App\Notifications;

use App\Models\OAuthNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OAuthMaintenanceModeNotification extends Notification implements ShouldQueue
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
        $entering = $data['maintenance_mode'] ?? true;
        $reason = $data['reason'] ?? null;
        $timestamp = $data['timestamp'] ?? now()->toISOString();

        $action = $entering ? 'Entered' : 'Exited';
        $icon = $entering ? '🔧' : '✅';
        $severity = $entering ? 'INFO' : 'RESOLVED';
        $color = $entering ? 'warning' : 'success';

        $message = (new MailMessage)
            ->subject("[{$severity}] OAuth Client {$action} Maintenance Mode: {$client->name}")
            ->greeting("{$icon} Maintenance Mode {$action}")
            ->line("The OAuth client **{$client->name}** has {$action} maintenance mode.")
            ->line("**Details:**")
            ->line("• **Client:** {$client->name}")
            ->line("• **Environment:** " . ucfirst($client->environment))
            ->line("• **Action:** {$action} maintenance mode")
            ->line("• **Timestamp:** " . date('Y-m-d H:i:s', strtotime($timestamp)));

        if ($entering && $reason) {
            $message->line("• **Reason:** {$reason}");
        }

        $message->line('');

        if ($entering) {
            $message->line("**Impact:**")
                ->line('• OAuth authentication requests may be affected')
                ->line('• Users may experience service disruptions')
                ->line('• Monitoring and health checks are disabled')
                ->line('')
                ->line("**What to expect:**")
                ->line('• The client will not serve OAuth requests during maintenance')
                ->line('• All health monitoring will be paused')
                ->line('• You will receive a notification when maintenance mode is exited');
        } else {
            $message->line("**Service Restored:**")
                ->line('• OAuth authentication is now available')
                ->line('• Health monitoring has resumed')
                ->line('• Normal operations have been restored');
        }

        return $message
            ->action('View Client Details', url("/oauth-clients/{$client->id}"))
            ->line('')
            ->line('This notification was generated automatically by the OAuth management system.')
            ->salutation('OAuth Management System');
    }

    public function toArray($notifiable): array
    {
        return [
            'notification_id' => $this->oauthNotification->id,
            'client_id' => $this->oauthNotification->oauth_client_id,
            'client_name' => $this->oauthNotification->oauthClient?->name,
            'type' => 'maintenance_mode',
            'maintenance_mode' => $this->oauthNotification->data['maintenance_mode'] ?? true,
            'reason' => $this->oauthNotification->data['reason'] ?? null,
        ];
    }
}