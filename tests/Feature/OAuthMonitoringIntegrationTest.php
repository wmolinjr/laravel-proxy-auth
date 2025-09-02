<?php

namespace Tests\Feature;

use App\Jobs\OAuth\OAuthHealthCheckJob;
use App\Models\OAuth\OAuthClient;
use App\Models\OAuthAlertRule;
use App\Models\OAuthNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OAuthMonitoringIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create admin user for tests
        $this->admin = User::factory()->create([
            'email' => 'admin@test.com',
        ]);
        
        // Seed default alert rules
        $this->artisan('db:seed', ['--class' => 'OAuthAlertRulesSeeder']);
    }

    public function test_complete_health_check_monitoring_flow()
    {
        Mail::fake();
        Http::fake([
            'https://test-client.com/health' => Http::response('', 500), // Simulate failure
        ]);

        // Create OAuth client with health monitoring enabled
        $client = OAuthClient::factory()->create([
            'name' => 'Test Integration Client',
            'health_check_enabled' => true,
            'health_check_url' => 'https://test-client.com/health',
            'health_check_interval' => 300,
            'health_check_failures' => 2, // Already has 2 failures
            'health_status' => 'unhealthy',
        ]);

        // Run health check job
        $job = new OAuthHealthCheckJob($client->id);
        $job->handle(
            app(\App\Services\OAuth\OAuthClientService::class),
            app(\App\Services\OAuth\OAuthNotificationService::class)
        );

        // Refresh client to see updated data
        $client->refresh();

        // Assert health check was performed and failed
        $this->assertEquals(3, $client->health_check_failures);
        $this->assertEquals('error', $client->health_status);

        // Assert notification was created (3+ failures trigger alert)
        $this->assertDatabaseHas('oauth_notifications', [
            'oauth_client_id' => $client->id,
            'type' => 'critical',
            'title' => 'Health Check Failure',
        ]);
    }

    public function test_error_rate_monitoring_triggers_alert()
    {
        Mail::fake();

        $client = OAuthClient::factory()->create(['name' => 'High Error Client']);

        // Simulate high error rate (above 10% threshold)
        $notificationService = app(\App\Services\OAuth\OAuthNotificationService::class);
        $notificationService->checkErrorRate($client, 25.7);

        // Assert notification was created
        $this->assertDatabaseHas('oauth_notifications', [
            'oauth_client_id' => $client->id,
            'type' => 'alert',
            'title' => 'High Error Rate Detected',
        ]);

        // Get the notification to check data
        $notification = OAuthNotification::where('oauth_client_id', $client->id)->first();
        $this->assertEquals(25.7, $notification->data['error_rate_percent']);
    }

    public function test_maintenance_mode_notifications()
    {
        $client = OAuthClient::factory()->create(['name' => 'Maintenance Client']);
        $notificationService = app(\App\Services\OAuth\OAuthNotificationService::class);

        // Test entering maintenance mode
        $notificationService->notifyMaintenanceMode($client, true, 'Scheduled system upgrade');

        $this->assertDatabaseHas('oauth_notifications', [
            'oauth_client_id' => $client->id,
            'type' => 'info',
            'title' => 'Maintenance Mode Entered',
        ]);
    }

    public function test_notification_acknowledgment_flow()
    {
        $client = OAuthClient::factory()->create();
        
        $notification = OAuthNotification::factory()->create([
            'oauth_client_id' => $client->id,
            'acknowledged_at' => null,
        ]);

        $this->assertFalse($notification->isAcknowledged());

        // Acknowledge the notification
        $notification->acknowledge($this->admin, 'Issue resolved');
        $notification->refresh();

        $this->assertTrue($notification->isAcknowledged());
        $this->assertEquals($this->admin->id, $notification->acknowledged_by);
        $this->assertEquals('Issue resolved', $notification->acknowledgment_note);
        $this->assertNotNull($notification->acknowledged_at);
    }
}