<?php

namespace Tests\Unit;

use App\Models\OAuth\OAuthClient;
use App\Models\OAuthAlertRule;
use App\Models\OAuthNotification;
use App\Models\User;
use App\Services\OAuth\OAuthNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OAuthNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private OAuthNotificationService $notificationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->notificationService = new OAuthNotificationService();
    }

    public function test_check_health_check_failures()
    {
        $client = OAuthClient::factory()->create([
            'health_check_enabled' => true,
            'health_check_failures' => 5,
            'health_status' => 'unhealthy',
        ]);

        OAuthAlertRule::factory()->create([
            'trigger_type' => 'health_check_failure',
            'conditions' => [
                ['field' => 'consecutive_failures', 'operator' => '>=', 'threshold' => 3]
            ],
            'is_active' => true,
        ]);

        $this->notificationService->checkHealthCheckFailures($client);

        $this->assertDatabaseHas('oauth_notifications', [
            'oauth_client_id' => $client->id,
            'type' => 'critical',
        ]);
    }

    public function test_check_error_rate()
    {
        $client = OAuthClient::factory()->create();

        OAuthAlertRule::factory()->create([
            'trigger_type' => 'high_error_rate',
            'conditions' => [
                ['field' => 'error_rate_percent', 'operator' => '>', 'threshold' => 10]
            ],
            'is_active' => true,
        ]);

        $this->notificationService->checkErrorRate($client, 25.5);

        $this->assertDatabaseHas('oauth_notifications', [
            'oauth_client_id' => $client->id,
            'type' => 'alert',
        ]);
    }

    public function test_notify_maintenance_mode()
    {
        $client = OAuthClient::factory()->create();

        OAuthAlertRule::factory()->create([
            'trigger_type' => 'maintenance_mode',
            'conditions' => [
                ['field' => 'maintenance_mode', 'operator' => 'in', 'threshold' => [true, false]]
            ],
            'is_active' => true,
        ]);

        $this->notificationService->notifyMaintenanceMode($client, true, 'Scheduled maintenance');

        $this->assertDatabaseHas('oauth_notifications', [
            'oauth_client_id' => $client->id,
            'type' => 'info',
            'title' => 'Maintenance Mode Entered',
        ]);
    }

    public function test_creates_health_check_alert_notification()
    {
        $client = OAuthClient::factory()->create(['name' => 'Test Client']);
        $rule = OAuthAlertRule::factory()->create();

        $notification = OAuthNotification::createHealthCheckAlert($client, 5, $rule);

        $this->assertInstanceOf(OAuthNotification::class, $notification);
        $this->assertEquals($client->id, $notification->oauth_client_id);
        $this->assertEquals($rule->id, $notification->alert_rule_id);
        $this->assertEquals('critical', $notification->type);
        $this->assertEquals('Health Check Failure', $notification->title);
        $this->assertStringContains('Test Client', $notification->message);
        $this->assertStringContains('5 consecutive times', $notification->message);
        $this->assertEquals(5, $notification->data['consecutive_failures']);
    }
}