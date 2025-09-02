<?php

namespace Tests\Unit\Models;

use App\Models\OAuth\OAuthClient;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OAuthClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_oauth_client_can_be_created_with_factory()
    {
        $client = OAuthClient::factory()->create();

        $this->assertNotNull($client->id);
        $this->assertNotNull($client->identifier);
        $this->assertNotNull($client->name);
        $this->assertIsArray($client->redirect_uris);
        $this->assertIsArray($client->grants);
        $this->assertIsArray($client->scopes);
    }

    public function test_oauth_client_fillable_attributes()
    {
        $client = new OAuthClient();
        
        $fillableAttributes = [
            'id', 'identifier', 'name', 'description', 'secret', 'redirect',
            'redirect_uris', 'grants', 'scopes', 'is_confidential',
            'personal_access_client', 'password_client', 'revoked',
            'health_check_url', 'health_check_interval', 'health_check_enabled',
            'health_check_failures', 'last_health_check', 'health_status',
            'last_error_message', 'last_activity_at', 'is_active',
            'maintenance_mode', 'maintenance_message', 'environment',
            'tags', 'contact_email', 'website_url', 'max_concurrent_tokens',
            'rate_limit_per_minute', 'version', 'created_by', 'updated_by'
        ];

        foreach ($fillableAttributes as $attribute) {
            $this->assertContains($attribute, $client->getFillable());
        }
    }

    public function test_oauth_client_casts_attributes_correctly()
    {
        $client = OAuthClient::factory()->create([
            'redirect_uris' => ['https://example.com/callback'],
            'grants' => ['authorization_code'],
            'scopes' => ['openid'],
            'is_confidential' => true,
            'health_check_enabled' => true,
            'is_active' => true,
            'maintenance_mode' => false,
        ]);

        $this->assertIsArray($client->redirect_uris);
        $this->assertIsArray($client->grants);
        $this->assertIsArray($client->scopes);
        $this->assertIsBool($client->is_confidential);
        $this->assertIsBool($client->health_check_enabled);
        $this->assertIsBool($client->is_active);
        $this->assertIsBool($client->maintenance_mode);
    }

    public function test_oauth_client_hides_secret_attribute()
    {
        $client = OAuthClient::factory()->confidential()->create();
        
        $this->assertArrayHasKey('secret', $client->getAttributes());
        $this->assertArrayNotHasKey('secret', $client->toArray());
    }

    public function test_get_redirect_uris_returns_array()
    {
        $uris = ['https://example.com/callback', 'https://app.example.com/callback'];
        $client = OAuthClient::factory()->withRedirectUris($uris)->create();

        $this->assertEquals($uris, $client->getRedirectUris());
    }

    public function test_get_redirect_uris_fallback_to_redirect_column()
    {
        $client = OAuthClient::factory()->create([
            'redirect' => 'https://example.com/callback,https://app.example.com/callback',
            'redirect_uris' => null,
        ]);

        $expected = ['https://example.com/callback', 'https://app.example.com/callback'];
        $this->assertEquals($expected, $client->getRedirectUris());
    }

    public function test_set_redirect_uris_updates_both_columns()
    {
        $client = OAuthClient::factory()->create();
        $uris = ['https://new.example.com/callback'];

        $client->setRedirectUris($uris);

        $this->assertEquals($uris, $client->redirect_uris);
        $this->assertEquals('https://new.example.com/callback', $client->redirect);
    }

    public function test_is_valid_redirect_uri_validates_correctly()
    {
        $uris = ['https://example.com/callback', 'https://app.example.com/callback'];
        $client = OAuthClient::factory()->withRedirectUris($uris)->create();

        $this->assertTrue($client->isValidRedirectUri('https://example.com/callback'));
        $this->assertTrue($client->isValidRedirectUri('https://app.example.com/callback'));
        $this->assertFalse($client->isValidRedirectUri('https://malicious.com/callback'));
    }

    public function test_active_scope_returns_only_non_revoked_clients()
    {
        $activeClient = OAuthClient::factory()->create(['revoked' => false]);
        $revokedClient = OAuthClient::factory()->revoked()->create();

        $activeClients = OAuthClient::active()->get();

        $this->assertTrue($activeClients->contains($activeClient));
        $this->assertFalse($activeClients->contains($revokedClient));
    }

    public function test_enabled_scope_returns_active_and_non_revoked_clients()
    {
        $enabledClient = OAuthClient::factory()->create(['revoked' => false, 'is_active' => true]);
        $inactiveClient = OAuthClient::factory()->inactive()->create();
        $revokedClient = OAuthClient::factory()->revoked()->create();

        $enabledClients = OAuthClient::enabled()->get();

        $this->assertTrue($enabledClients->contains($enabledClient));
        $this->assertFalse($enabledClients->contains($inactiveClient));
        $this->assertFalse($enabledClients->contains($revokedClient));
    }

    public function test_in_maintenance_scope_returns_maintenance_clients()
    {
        $normalClient = OAuthClient::factory()->create();
        $maintenanceClient = OAuthClient::factory()->maintenance()->create();

        $maintenanceClients = OAuthClient::inMaintenance()->get();

        $this->assertFalse($maintenanceClients->contains($normalClient));
        $this->assertTrue($maintenanceClients->contains($maintenanceClient));
    }

    public function test_health_status_scope_filters_by_status()
    {
        $healthyClient = OAuthClient::factory()->healthy()->create();
        $unhealthyClient = OAuthClient::factory()->unhealthy()->create();
        $errorClient = OAuthClient::factory()->healthError()->create();

        $healthyClients = OAuthClient::healthStatus('healthy')->get();
        $unhealthyClients = OAuthClient::healthStatus('unhealthy')->get();
        $errorClients = OAuthClient::healthStatus('error')->get();

        $this->assertTrue($healthyClients->contains($healthyClient));
        $this->assertFalse($healthyClients->contains($unhealthyClient));

        $this->assertTrue($unhealthyClients->contains($unhealthyClient));
        $this->assertFalse($unhealthyClients->contains($healthyClient));

        $this->assertTrue($errorClients->contains($errorClient));
        $this->assertFalse($errorClients->contains($healthyClient));
    }

    public function test_unhealthy_scope_returns_unhealthy_and_error_clients()
    {
        $healthyClient = OAuthClient::factory()->healthy()->create();
        $unhealthyClient = OAuthClient::factory()->unhealthy()->create();
        $errorClient = OAuthClient::factory()->healthError()->create();

        $unhealthyClients = OAuthClient::unhealthy()->get();

        $this->assertFalse($unhealthyClients->contains($healthyClient));
        $this->assertTrue($unhealthyClients->contains($unhealthyClient));
        $this->assertTrue($unhealthyClients->contains($errorClient));
    }

    public function test_is_healthy_returns_correct_boolean()
    {
        $healthyClient = OAuthClient::factory()->healthy()->create();
        $unhealthyClient = OAuthClient::factory()->unhealthy()->create();

        $this->assertTrue($healthyClient->isHealthy());
        $this->assertFalse($unhealthyClient->isHealthy());
    }

    public function test_needs_health_check_when_disabled()
    {
        $client = OAuthClient::factory()->create(['health_check_enabled' => false]);

        $this->assertFalse($client->needsHealthCheck());
    }

    public function test_needs_health_check_when_no_url()
    {
        $client = OAuthClient::factory()->create([
            'health_check_enabled' => true,
            'health_check_url' => null,
        ]);

        $this->assertFalse($client->needsHealthCheck());
    }

    public function test_needs_health_check_when_never_checked()
    {
        $client = OAuthClient::factory()->create([
            'health_check_enabled' => true,
            'health_check_url' => 'https://example.com/health',
            'last_health_check' => null,
        ]);

        $this->assertTrue($client->needsHealthCheck());
    }

    public function test_needs_health_check_when_interval_passed()
    {
        $client = OAuthClient::factory()->create([
            'health_check_enabled' => true,
            'health_check_url' => 'https://example.com/health',
            'health_check_interval' => 300, // 5 minutes
            'last_health_check' => now()->subMinutes(10), // 10 minutes ago
        ]);

        $this->assertTrue($client->needsHealthCheck());
    }

    public function test_needs_health_check_when_interval_not_passed()
    {
        $client = OAuthClient::factory()->create([
            'health_check_enabled' => true,
            'health_check_url' => 'https://example.com/health',
            'health_check_interval' => 300, // 5 minutes
            'last_health_check' => now()->subMinutes(2), // 2 minutes ago
        ]);

        $this->assertFalse($client->needsHealthCheck());
    }

    public function test_is_in_maintenance_mode_returns_correct_boolean()
    {
        $normalClient = OAuthClient::factory()->create(['maintenance_mode' => false]);
        $maintenanceClient = OAuthClient::factory()->maintenance()->create();

        $this->assertFalse($normalClient->isInMaintenanceMode());
        $this->assertTrue($maintenanceClient->isInMaintenanceMode());
    }

    public function test_touch_activity_updates_last_activity_at()
    {
        $client = OAuthClient::factory()->create(['last_activity_at' => null]);

        $this->assertNull($client->last_activity_at);

        $client->touchActivity();
        $client->refresh();

        $this->assertNotNull($client->last_activity_at);
        $this->assertTrue(Carbon::parse($client->last_activity_at)->isToday());
    }

    public function test_creator_relationship()
    {
        $user = User::factory()->create();
        $client = OAuthClient::factory()->create(['created_by' => $user->id]);

        $this->assertInstanceOf(User::class, $client->creator);
        $this->assertEquals($user->id, $client->creator->id);
    }

    public function test_updater_relationship()
    {
        $user = User::factory()->create();
        $client = OAuthClient::factory()->create(['updated_by' => $user->id]);

        $this->assertInstanceOf(User::class, $client->updater);
        $this->assertEquals($user->id, $client->updater->id);
    }

    public function test_factory_states_work_correctly()
    {
        $confidentialClient = OAuthClient::factory()->confidential()->create();
        $this->assertTrue($confidentialClient->is_confidential);
        $this->assertNotNull($confidentialClient->secret);

        $publicClient = OAuthClient::factory()->public()->create();
        $this->assertFalse($publicClient->is_confidential);
        $this->assertNull($publicClient->secret);

        $revokedClient = OAuthClient::factory()->revoked()->create();
        $this->assertTrue($revokedClient->revoked);
        $this->assertFalse($revokedClient->is_active);

        $inactiveClient = OAuthClient::factory()->inactive()->create();
        $this->assertFalse($inactiveClient->is_active);

        $maintenanceClient = OAuthClient::factory()->maintenance()->create();
        $this->assertTrue($maintenanceClient->maintenance_mode);
        $this->assertNotNull($maintenanceClient->maintenance_message);

        $healthyClient = OAuthClient::factory()->healthy()->create();
        $this->assertEquals('healthy', $healthyClient->health_status);
        $this->assertTrue($healthyClient->health_check_enabled);
        $this->assertEquals(0, $healthyClient->health_check_failures);

        $unhealthyClient = OAuthClient::factory()->unhealthy()->create();
        $this->assertEquals('unhealthy', $unhealthyClient->health_status);
        $this->assertGreaterThan(0, $unhealthyClient->health_check_failures);

        $errorClient = OAuthClient::factory()->healthError()->create();
        $this->assertEquals('error', $errorClient->health_status);
        $this->assertGreaterThan(0, $errorClient->health_check_failures);

        $productionClient = OAuthClient::factory()->production()->create();
        $this->assertEquals('production', $productionClient->environment);

        $stagingClient = OAuthClient::factory()->staging()->create();
        $this->assertEquals('staging', $stagingClient->environment);

        $developmentClient = OAuthClient::factory()->development()->create();
        $this->assertEquals('development', $developmentClient->environment);
    }

    public function test_factory_with_methods_work_correctly()
    {
        $grants = ['client_credentials', 'refresh_token'];
        $client = OAuthClient::factory()->withGrants($grants)->create();
        $this->assertEquals($grants, $client->grants);

        $scopes = ['api', 'admin'];
        $client = OAuthClient::factory()->withScopes($scopes)->create();
        $this->assertEquals($scopes, $client->scopes);

        $uris = ['https://test.com/callback'];
        $client = OAuthClient::factory()->withRedirectUris($uris)->create();
        $this->assertEquals($uris, $client->redirect_uris);

        $client = OAuthClient::factory()->withHealthCheck('https://test.com/health', 600)->create();
        $this->assertTrue($client->health_check_enabled);
        $this->assertEquals('https://test.com/health', $client->health_check_url);
        $this->assertEquals(600, $client->health_check_interval);
    }
}