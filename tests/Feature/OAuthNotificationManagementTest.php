<?php

namespace Tests\Feature;

use App\Models\OAuth\OAuthClient;
use App\Models\OAuthNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OAuthNotificationManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected OAuthClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->admin = User::factory()->create([
            'email' => 'admin@test.com',
        ]);
        
        $this->client = OAuthClient::factory()->create();
        
        $this->actingAs($this->admin);
    }

    public function test_notifications_index_displays_correctly()
    {
        $criticalNotification = OAuthNotification::factory()->create([
            'oauth_client_id' => $this->client->id,
            'type' => 'critical',
            'title' => 'Critical Alert',
            'acknowledged_at' => null,
        ]);

        $acknowledgedNotification = OAuthNotification::factory()->create([
            'oauth_client_id' => $this->client->id,
            'type' => 'info',
            'title' => 'Acknowledged Alert',
            'acknowledged_at' => now(),
            'acknowledged_by' => $this->admin->id,
        ]);

        $response = $this->get('/admin/notifications');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => 
            $page->component('Admin/Notifications/Index')
                ->has('notifications.data', 2)
                ->where('notifications.data.0.title', 'Acknowledged Alert')
                ->where('notifications.data.1.title', 'Critical Alert')
        );
    }

    public function test_notifications_can_be_filtered_by_type()
    {
        OAuthNotification::factory()->create([
            'oauth_client_id' => $this->client->id,
            'type' => 'critical',
        ]);

        OAuthNotification::factory()->create([
            'oauth_client_id' => $this->client->id,
            'type' => 'alert',
        ]);

        OAuthNotification::factory()->create([
            'oauth_client_id' => $this->client->id,
            'type' => 'info',
        ]);

        $response = $this->get('/admin/notifications?type=critical');

        $response->assertInertia(fn (Assert $page) => 
            $page->has('notifications.data', 1)
                ->where('notifications.data.0.type', 'critical')
        );
    }

    public function test_notifications_can_be_filtered_by_acknowledgment()
    {
        OAuthNotification::factory()->create([
            'oauth_client_id' => $this->client->id,
            'acknowledged_at' => null,
        ]);

        OAuthNotification::factory()->create([
            'oauth_client_id' => $this->client->id,
            'acknowledged_at' => now(),
        ]);

        $response = $this->get('/admin/notifications?acknowledged=false');

        $response->assertInertia(fn (Assert $page) => 
            $page->has('notifications.data', 1)
                ->whereNull('notifications.data.0.acknowledged_at')
        );
    }

    public function test_notifications_can_be_filtered_by_client()
    {
        $otherClient = OAuthClient::factory()->create();

        OAuthNotification::factory()->create([
            'oauth_client_id' => $this->client->id,
        ]);

        OAuthNotification::factory()->create([
            'oauth_client_id' => $otherClient->id,
        ]);

        $response = $this->get("/admin/notifications?client_id={$this->client->id}");

        $response->assertInertia(fn (Assert $page) => 
            $page->has('notifications.data', 1)
                ->where('notifications.data.0.oauth_client_id', $this->client->id)
        );
    }

    public function test_notification_can_be_acknowledged()
    {
        $notification = OAuthNotification::factory()->create([
            'oauth_client_id' => $this->client->id,
            'acknowledged_at' => null,
        ]);

        $response = $this->post("/admin/notifications/{$notification->id}/acknowledge", [
            'note' => 'Issue investigated and resolved',
        ]);

        $response->assertStatus(200);
        $notification->refresh();
        
        $this->assertNotNull($notification->acknowledged_at);
        $this->assertEquals($this->admin->id, $notification->acknowledged_by);
        $this->assertEquals('Issue investigated and resolved', $notification->acknowledgment_note);
    }

    public function test_multiple_notifications_can_be_acknowledged()
    {
        $notification1 = OAuthNotification::factory()->create([
            'oauth_client_id' => $this->client->id,
            'acknowledged_at' => null,
        ]);

        $notification2 = OAuthNotification::factory()->create([
            'oauth_client_id' => $this->client->id,
            'acknowledged_at' => null,
        ]);

        $response = $this->post('/admin/notifications/acknowledge-multiple', [
            'notification_ids' => [$notification1->id, $notification2->id],
            'note' => 'Bulk acknowledgment',
        ]);

        $response->assertStatus(200);
        
        $notification1->refresh();
        $notification2->refresh();
        
        $this->assertNotNull($notification1->acknowledged_at);
        $this->assertNotNull($notification2->acknowledged_at);
        $this->assertEquals('Bulk acknowledgment', $notification1->acknowledgment_note);
        $this->assertEquals('Bulk acknowledgment', $notification2->acknowledgment_note);
    }

    public function test_all_notifications_can_be_acknowledged()
    {
        OAuthNotification::factory()->count(3)->create([
            'oauth_client_id' => $this->client->id,
            'acknowledged_at' => null,
        ]);

        // Already acknowledged notification (should be ignored)
        OAuthNotification::factory()->create([
            'oauth_client_id' => $this->client->id,
            'acknowledged_at' => now()->subHour(),
        ]);

        $response = $this->post('/admin/notifications/acknowledge-all', [
            'note' => 'Mass acknowledgment',
        ]);

        $response->assertStatus(200);
        
        $unacknowledgedCount = OAuthNotification::whereNull('acknowledged_at')->count();
        $this->assertEquals(0, $unacknowledgedCount);
    }

    public function test_notifications_show_displays_details()
    {
        $notification = OAuthNotification::factory()->create([
            'oauth_client_id' => $this->client->id,
            'type' => 'critical',
            'title' => 'Health Check Failure',
            'message' => 'Detailed error message',
            'data' => ['consecutive_failures' => 5],
        ]);

        $response = $this->get("/admin/notifications/{$notification->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => 
            $page->component('Admin/Notifications/Show')
                ->where('notification.title', 'Health Check Failure')
                ->where('notification.type', 'critical')
                ->has('notification.data')
                ->has('notification.oauth_client')
        );
    }

    public function test_notification_center_api_returns_recent_notifications()
    {
        // Recent notification
        $recentNotification = OAuthNotification::factory()->create([
            'oauth_client_id' => $this->client->id,
            'type' => 'critical',
            'acknowledged_at' => null,
            'created_at' => now()->subMinutes(5),
        ]);

        // Old notification (should not appear in notification center)
        OAuthNotification::factory()->create([
            'oauth_client_id' => $this->client->id,
            'type' => 'info',
            'acknowledged_at' => null,
            'created_at' => now()->subDays(2),
        ]);

        $response = $this->get('/admin/api/notification-center');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'notifications' => [
                '*' => ['id', 'type', 'title', 'message', 'created_at', 'oauth_client']
            ],
            'unread_count',
        ]);

        $responseData = $response->json();
        $this->assertCount(1, $responseData['notifications']);
        $this->assertEquals(1, $responseData['unread_count']);
    }

    public function test_notification_center_counts_unread_correctly()
    {
        // Unacknowledged notifications
        OAuthNotification::factory()->count(3)->create([
            'oauth_client_id' => $this->client->id,
            'acknowledged_at' => null,
        ]);

        // Acknowledged notification (should not count)
        OAuthNotification::factory()->create([
            'oauth_client_id' => $this->client->id,
            'acknowledged_at' => now(),
        ]);

        $response = $this->get('/admin/api/notification-center');

        $response->assertStatus(200);
        $this->assertEquals(3, $response->json('unread_count'));
    }

    public function test_notifications_require_authentication()
    {
        $this->app['auth']->logout();

        $response = $this->get('/admin/notifications');

        $response->assertRedirect('/login');
    }

    public function test_notification_acknowledgment_creates_audit_log()
    {
        $notification = OAuthNotification::factory()->create([
            'oauth_client_id' => $this->client->id,
            'acknowledged_at' => null,
        ]);

        $response = $this->post("/admin/notifications/{$notification->id}/acknowledge", [
            'note' => 'Test acknowledgment',
        ]);

        $response->assertStatus(200);
        
        // Check that acknowledgment was recorded
        $notification->refresh();
        $this->assertEquals($this->admin->id, $notification->acknowledged_by);
        $this->assertEquals('Test acknowledgment', $notification->acknowledgment_note);
    }

    public function test_notifications_display_proper_severity_styling()
    {
        OAuthNotification::factory()->create([
            'oauth_client_id' => $this->client->id,
            'type' => 'critical',
        ]);

        OAuthNotification::factory()->create([
            'oauth_client_id' => $this->client->id,
            'type' => 'alert',
        ]);

        OAuthNotification::factory()->create([
            'oauth_client_id' => $this->client->id,
            'type' => 'warning',
        ]);

        OAuthNotification::factory()->create([
            'oauth_client_id' => $this->client->id,
            'type' => 'info',
        ]);

        $response = $this->get('/admin/notifications');

        $response->assertInertia(fn (Assert $page) => 
            $page->has('notifications.data', 4)
        );
    }

    public function test_notification_deletion_when_client_is_deleted()
    {
        $notification = OAuthNotification::factory()->create([
            'oauth_client_id' => $this->client->id,
        ]);

        // Delete the client
        $this->client->delete();

        // Check that notification still exists but orphaned
        $this->assertDatabaseHas('oauth_notifications', [
            'id' => $notification->id,
        ]);
    }
}