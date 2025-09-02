<?php

namespace Tests\Feature;

use App\Models\OAuth\OAuthClient;
use App\Models\OAuth\OAuthClientUsage;
use App\Models\OAuthNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OAuthDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->admin = User::factory()->create([
            'email' => 'admin@test.com',
        ]);
        
        $this->actingAs($this->admin);
    }

    public function test_dashboard_displays_correctly()
    {
        // Create test data
        $healthyClient = OAuthClient::factory()->create([
            'health_status' => 'healthy',
            'is_active' => true,
        ]);

        $unhealthyClient = OAuthClient::factory()->create([
            'health_status' => 'unhealthy',
            'is_active' => true,
        ]);

        $maintenanceClient = OAuthClient::factory()->create([
            'maintenance_mode' => true,
        ]);

        // Create notifications
        $notification = OAuthNotification::factory()->create([
            'oauth_client_id' => $unhealthyClient->id,
            'acknowledged_at' => null,
        ]);

        $response = $this->get('/admin/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => 
            $page->component('Admin/Dashboard')
                ->has('stats.oauth_clients')
                ->has('stats.notifications')
                ->has('recentNotifications')
                ->has('clientsOverview')
        );
    }

    public function test_dashboard_stats_calculation()
    {
        // Create clients with different statuses
        OAuthClient::factory()->count(5)->create(['health_status' => 'healthy']);
        OAuthClient::factory()->count(3)->create(['health_status' => 'unhealthy']);
        OAuthClient::factory()->count(2)->create(['health_status' => 'error']);
        OAuthClient::factory()->count(1)->create(['maintenance_mode' => true]);

        // Create unacknowledged notifications
        OAuthNotification::factory()->count(4)->create([
            'acknowledged_at' => null,
            'type' => 'critical',
        ]);

        $response = $this->get('/admin/dashboard');

        $response->assertInertia(fn (Assert $page) => 
            $page->where('stats.oauth_clients.total', 11)
                ->where('stats.oauth_clients.healthy', 5)
                ->where('stats.oauth_clients.unhealthy', 3)
                ->where('stats.oauth_clients.error', 2)
                ->where('stats.oauth_clients.maintenance', 1)
                ->where('stats.notifications.unacknowledged', 4)
        );
    }

    public function test_dashboard_recent_notifications()
    {
        $client = OAuthClient::factory()->create();

        // Create recent and old notifications
        $recentNotification = OAuthNotification::factory()->create([
            'oauth_client_id' => $client->id,
            'type' => 'critical',
            'title' => 'Recent Alert',
            'created_at' => now()->subMinutes(5),
        ]);

        $oldNotification = OAuthNotification::factory()->create([
            'oauth_client_id' => $client->id,
            'type' => 'info',
            'title' => 'Old Alert',
            'created_at' => now()->subDays(5),
        ]);

        $response = $this->get('/admin/dashboard');

        $response->assertInertia(fn (Assert $page) => 
            $page->has('recentNotifications', 1)
                ->where('recentNotifications.0.title', 'Recent Alert')
        );
    }

    public function test_dashboard_clients_overview()
    {
        $productionClient = OAuthClient::factory()->create([
            'environment' => 'production',
            'health_status' => 'healthy',
        ]);

        $stagingClient = OAuthClient::factory()->create([
            'environment' => 'staging', 
            'health_status' => 'unhealthy',
        ]);

        // Create usage stats
        OAuthClientUsage::factory()->create([
            'client_id' => $productionClient->id,
            'date' => now()->toDateString(),
            'total_requests' => 1500,
            'successful_requests' => 1485,
            'failed_requests' => 15,
        ]);

        $response = $this->get('/admin/dashboard');

        $response->assertInertia(fn (Assert $page) => 
            $page->has('clientsOverview')
                ->has('clientsOverview.0.id')
                ->has('clientsOverview.0.name')
                ->has('clientsOverview.0.health_status')
                ->has('clientsOverview.0.environment')
        );
    }

    public function test_dashboard_performance_metrics()
    {
        $client = OAuthClient::factory()->create();

        // Create usage data for trend calculation
        OAuthClientUsage::factory()->create([
            'client_id' => $client->id,
            'date' => now()->toDateString(),
            'total_requests' => 1000,
            'successful_requests' => 980,
            'failed_requests' => 20,
            'avg_response_time' => 150,
        ]);

        OAuthClientUsage::factory()->create([
            'client_id' => $client->id,
            'date' => now()->subDay()->toDateString(),
            'total_requests' => 800,
            'successful_requests' => 790,
            'failed_requests' => 10,
            'avg_response_time' => 120,
        ]);

        $response = $this->get('/admin/dashboard');

        $response->assertInertia(fn (Assert $page) => 
            $page->has('stats.system_performance')
        );
    }

    public function test_dashboard_requires_authentication()
    {
        $this->app['auth']->logout();

        $response = $this->get('/admin/dashboard');

        $response->assertRedirect('/login');
    }

    public function test_dashboard_health_status_colors()
    {
        OAuthClient::factory()->create(['health_status' => 'healthy']);
        OAuthClient::factory()->create(['health_status' => 'unhealthy']);
        OAuthClient::factory()->create(['health_status' => 'error']);
        OAuthClient::factory()->create(['health_status' => 'unknown']);

        $response = $this->get('/admin/dashboard');

        $response->assertInertia(fn (Assert $page) => 
            $page->has('clientsOverview')
                ->where('stats.oauth_clients.healthy', 1)
                ->where('stats.oauth_clients.unhealthy', 1)
                ->where('stats.oauth_clients.error', 1)
                ->where('stats.oauth_clients.unknown', 1)
        );
    }

    public function test_dashboard_handles_empty_state()
    {
        // No clients or notifications created

        $response = $this->get('/admin/dashboard');

        $response->assertInertia(fn (Assert $page) => 
            $page->where('stats.oauth_clients.total', 0)
                ->where('stats.notifications.unacknowledged', 0)
                ->has('recentNotifications', 0)
                ->has('clientsOverview', 0)
        );
    }

    public function test_dashboard_notification_severity_counts()
    {
        $client = OAuthClient::factory()->create();

        // Create notifications with different severities
        OAuthNotification::factory()->create([
            'oauth_client_id' => $client->id,
            'type' => 'critical',
            'acknowledged_at' => null,
        ]);

        OAuthNotification::factory()->count(2)->create([
            'oauth_client_id' => $client->id,
            'type' => 'alert',
            'acknowledged_at' => null,
        ]);

        OAuthNotification::factory()->create([
            'oauth_client_id' => $client->id,
            'type' => 'warning',
            'acknowledged_at' => null,
        ]);

        // Acknowledged notification (shouldn't count)
        OAuthNotification::factory()->create([
            'oauth_client_id' => $client->id,
            'type' => 'critical',
            'acknowledged_at' => now(),
        ]);

        $response = $this->get('/admin/dashboard');

        $response->assertInertia(fn (Assert $page) => 
            $page->where('stats.notifications.critical', 1)
                ->where('stats.notifications.alert', 2)
                ->where('stats.notifications.warning', 1)
                ->where('stats.notifications.unacknowledged', 4)
        );
    }
}