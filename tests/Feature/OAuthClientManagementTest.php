<?php

namespace Tests\Feature;

use App\Models\OAuth\OAuthClient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OAuthClientManagementTest extends TestCase
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

    public function test_oauth_clients_index_displays_correctly()
    {
        $healthyClient = OAuthClient::factory()->create([
            'name' => 'Healthy App',
            'health_status' => 'healthy',
            'environment' => 'production',
        ]);

        $unhealthyClient = OAuthClient::factory()->create([
            'name' => 'Unhealthy App',
            'health_status' => 'unhealthy',
            'environment' => 'staging',
        ]);

        $response = $this->get('/admin/oauth-clients');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => 
            $page->component('Admin/OAuthClients/Index')
                ->has('clients.data', 2)
                ->where('clients.data.0.name', 'Unhealthy App')
                ->where('clients.data.1.name', 'Healthy App')
        );
    }

    public function test_oauth_clients_can_be_filtered_by_status()
    {
        OAuthClient::factory()->create(['health_status' => 'healthy']);
        OAuthClient::factory()->create(['health_status' => 'unhealthy']);
        OAuthClient::factory()->create(['health_status' => 'error']);

        $response = $this->get('/admin/oauth-clients?health_status=healthy');

        $response->assertInertia(fn (Assert $page) => 
            $page->has('clients.data', 1)
                ->where('clients.data.0.health_status', 'healthy')
        );
    }

    public function test_oauth_clients_can_be_filtered_by_environment()
    {
        OAuthClient::factory()->create(['environment' => 'production']);
        OAuthClient::factory()->create(['environment' => 'staging']);
        OAuthClient::factory()->count(2)->create(['environment' => 'development']);

        $response = $this->get('/admin/oauth-clients?environment=development');

        $response->assertInertia(fn (Assert $page) => 
            $page->has('clients.data', 2)
                ->where('clients.data.0.environment', 'development')
                ->where('clients.data.1.environment', 'development')
        );
    }

    public function test_oauth_clients_can_be_searched()
    {
        OAuthClient::factory()->create(['name' => 'Customer Portal']);
        OAuthClient::factory()->create(['name' => 'Admin Dashboard']);
        OAuthClient::factory()->create(['name' => 'Mobile App']);

        $response = $this->get('/admin/oauth-clients?search=Portal');

        $response->assertInertia(fn (Assert $page) => 
            $page->has('clients.data', 1)
                ->where('clients.data.0.name', 'Customer Portal')
        );
    }

    public function test_oauth_client_show_displays_details()
    {
        $client = OAuthClient::factory()->create([
            'name' => 'Test Application',
            'description' => 'Test description',
            'redirect_uris' => ['https://test.com/callback'],
            'health_check_enabled' => true,
            'health_check_url' => 'https://test.com/health',
        ]);

        $response = $this->get("/admin/oauth-clients/{$client->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => 
            $page->component('Admin/OAuthClients/Show')
                ->where('client.name', 'Test Application')
                ->where('client.description', 'Test description')
                ->where('client.health_check_enabled', true)
                ->has('client.redirect_uris')
        );
    }

    public function test_oauth_client_create_form_displays()
    {
        $response = $this->get('/admin/oauth-clients/create');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => 
            $page->component('Admin/OAuthClients/Create')
                ->has('grants')
                ->has('scopes')
                ->has('environments')
        );
    }

    public function test_oauth_client_can_be_created()
    {
        $clientData = [
            'name' => 'New Test App',
            'description' => 'A new test application',
            'redirect_uris' => ['https://newapp.com/callback'],
            'grants' => ['authorization_code', 'refresh_token'],
            'scopes' => ['openid', 'profile'],
            'is_confidential' => true,
            'environment' => 'production',
            'health_check_enabled' => true,
            'health_check_url' => 'https://newapp.com/health',
            'health_check_interval' => 300,
            'contact_email' => 'admin@newapp.com',
        ];

        $response = $this->post('/admin/oauth-clients', $clientData);

        $response->assertRedirect();
        $this->assertDatabaseHas('oauth_clients', [
            'name' => 'New Test App',
            'health_check_enabled' => true,
            'health_check_url' => 'https://newapp.com/health',
        ]);
    }

    public function test_oauth_client_creation_validation()
    {
        $response = $this->post('/admin/oauth-clients', [
            'name' => '', // Required field
            'redirect_uris' => 'invalid-url', // Should be array
        ]);

        $response->assertSessionHasErrors(['name', 'redirect_uris']);
    }

    public function test_oauth_client_edit_form_displays()
    {
        $client = OAuthClient::factory()->create();

        $response = $this->get("/admin/oauth-clients/{$client->id}/edit");

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => 
            $page->component('Admin/OAuthClients/Edit')
                ->where('client.id', $client->id)
                ->has('grants')
                ->has('scopes')
                ->has('environments')
        );
    }

    public function test_oauth_client_can_be_updated()
    {
        $client = OAuthClient::factory()->create([
            'name' => 'Original Name',
            'health_check_enabled' => false,
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'description' => 'Updated description',
            'health_check_enabled' => true,
            'health_check_url' => 'https://updated.com/health',
        ];

        $response = $this->put("/admin/oauth-clients/{$client->id}", $updateData);

        $response->assertRedirect();
        $this->assertDatabaseHas('oauth_clients', [
            'id' => $client->id,
            'name' => 'Updated Name',
            'health_check_enabled' => true,
        ]);
    }

    public function test_oauth_client_status_can_be_toggled()
    {
        $client = OAuthClient::factory()->create(['is_active' => true]);

        $response = $this->post("/admin/oauth-clients/{$client->id}/toggle-status");

        $response->assertStatus(200);
        $client->refresh();
        $this->assertFalse($client->is_active);
    }

    public function test_oauth_client_maintenance_mode_can_be_toggled()
    {
        $client = OAuthClient::factory()->create(['maintenance_mode' => false]);

        $response = $this->post("/admin/oauth-clients/{$client->id}/toggle-maintenance", [
            'reason' => 'Scheduled maintenance',
        ]);

        $response->assertStatus(200);
        $client->refresh();
        $this->assertTrue($client->maintenance_mode);
        $this->assertEquals('Scheduled maintenance', $client->maintenance_message);
    }

    public function test_oauth_client_health_check_can_be_performed()
    {
        Http::fake([
            'https://test.com/health' => Http::response('OK', 200),
        ]);

        $client = OAuthClient::factory()->create([
            'health_check_enabled' => true,
            'health_check_url' => 'https://test.com/health',
        ]);

        $response = $this->post("/admin/oauth-clients/{$client->id}/health-check");

        $response->assertStatus(200);
        $response->assertJson([
            'health_status' => 'healthy',
            'status_code' => 200,
        ]);
    }

    public function test_oauth_client_health_check_handles_failure()
    {
        Http::fake([
            'https://test.com/health' => Http::response('', 500),
        ]);

        $client = OAuthClient::factory()->create([
            'health_check_enabled' => true,
            'health_check_url' => 'https://test.com/health',
            'health_status' => 'healthy',
            'health_check_failures' => 0,
        ]);

        $response = $this->post("/admin/oauth-clients/{$client->id}/health-check");

        $response->assertStatus(200);
        $client->refresh();
        $this->assertEquals('unhealthy', $client->health_status);
        $this->assertEquals(1, $client->health_check_failures);
    }

    public function test_oauth_client_secret_can_be_regenerated()
    {
        $client = OAuthClient::factory()->create();
        $originalSecret = $client->secret;

        $response = $this->post("/admin/oauth-clients/{$client->id}/regenerate-secret");

        $response->assertStatus(200);
        $response->assertJsonStructure(['secret', 'message']);
        
        $client->refresh();
        $this->assertNotEquals($originalSecret, $client->secret);
    }

    public function test_oauth_client_tokens_can_be_revoked()
    {
        $client = OAuthClient::factory()->create();

        $response = $this->post("/admin/oauth-clients/{$client->id}/revoke-tokens");

        $response->assertStatus(200);
        $response->assertJsonStructure(['revoked_count', 'message']);
    }

    public function test_oauth_client_can_be_deleted()
    {
        $client = OAuthClient::factory()->create();

        $response = $this->delete("/admin/oauth-clients/{$client->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('oauth_clients', [
            'id' => $client->id,
        ]);
    }

    public function test_oauth_client_analytics_displays()
    {
        $client = OAuthClient::factory()->create();

        $response = $this->get("/admin/oauth-clients/{$client->id}/analytics");

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => 
            $page->component('Admin/OAuthClients/Analytics')
                ->where('client.id', $client->id)
                ->has('analytics')
        );
    }

    public function test_oauth_client_events_displays()
    {
        $client = OAuthClient::factory()->create();

        $response = $this->get("/admin/oauth-clients/{$client->id}/events");

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => 
            $page->component('Admin/OAuthClients/Events')
                ->where('client.id', $client->id)
                ->has('events')
        );
    }

    public function test_unauthorized_user_cannot_access_oauth_clients()
    {
        $this->app['auth']->logout();

        $response = $this->get('/admin/oauth-clients');

        $response->assertRedirect('/login');
    }
}