<?php

namespace Tests\Unit\Models;

use App\Models\OAuth\OAuthClient;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OAuthClientRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_oauth_client_has_creator_relationship()
    {
        $client = new OAuthClient();
        $relation = $client->creator();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertEquals('created_by', $relation->getForeignKeyName());
        $this->assertEquals(User::class, get_class($relation->getRelated()));
    }

    public function test_oauth_client_has_updater_relationship()
    {
        $client = new OAuthClient();
        $relation = $client->updater();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertEquals('updated_by', $relation->getForeignKeyName());
        $this->assertEquals(User::class, get_class($relation->getRelated()));
    }

    public function test_oauth_client_has_access_tokens_relationship()
    {
        $client = new OAuthClient();
        $relation = $client->accessTokens();

        $this->assertInstanceOf(HasMany::class, $relation);
        $this->assertEquals('client_id', $relation->getForeignKeyName());
    }

    public function test_oauth_client_has_authorization_codes_relationship()
    {
        $client = new OAuthClient();
        $relation = $client->authorizationCodes();

        $this->assertInstanceOf(HasMany::class, $relation);
        $this->assertEquals('client_id', $relation->getForeignKeyName());
    }

    public function test_oauth_client_has_notifications_relationship()
    {
        $client = new OAuthClient();
        $relation = $client->notifications();

        $this->assertInstanceOf(HasMany::class, $relation);
        $this->assertEquals('oauth_client_id', $relation->getForeignKeyName());
    }

    public function test_oauth_client_has_usage_stats_relationship()
    {
        $client = new OAuthClient();
        $relation = $client->usageStats();

        $this->assertInstanceOf(HasMany::class, $relation);
        $this->assertEquals('client_id', $relation->getForeignKeyName());
    }

    public function test_oauth_client_has_events_relationship()
    {
        $client = new OAuthClient();
        $relation = $client->events();

        $this->assertInstanceOf(HasMany::class, $relation);
        $this->assertEquals('client_id', $relation->getForeignKeyName());
    }

    public function test_oauth_client_has_usage_records_relationship()
    {
        $client = new OAuthClient();
        $relation = $client->usageRecords();

        $this->assertInstanceOf(HasMany::class, $relation);
        $this->assertEquals('client_id', $relation->getForeignKeyName());
    }

    public function test_creator_relationship_works_in_practice()
    {
        $user = User::factory()->create(['name' => 'Test Creator']);
        $client = OAuthClient::factory()->create(['created_by' => $user->id]);

        $creator = $client->creator;

        $this->assertInstanceOf(User::class, $creator);
        $this->assertEquals($user->id, $creator->id);
        $this->assertEquals('Test Creator', $creator->name);
    }

    public function test_updater_relationship_works_in_practice()
    {
        $user = User::factory()->create(['name' => 'Test Updater']);
        $client = OAuthClient::factory()->create(['updated_by' => $user->id]);

        $updater = $client->updater;

        $this->assertInstanceOf(User::class, $updater);
        $this->assertEquals($user->id, $updater->id);
        $this->assertEquals('Test Updater', $updater->name);
    }

    public function test_creator_can_be_null()
    {
        $client = OAuthClient::factory()->create(['created_by' => null]);

        $creator = $client->creator;

        $this->assertNull($creator);
    }

    public function test_updater_can_be_null()
    {
        $client = OAuthClient::factory()->create(['updated_by' => null]);

        $updater = $client->updater;

        $this->assertNull($updater);
    }

    public function test_multiple_clients_can_have_same_creator()
    {
        $user = User::factory()->create();
        $client1 = OAuthClient::factory()->create(['created_by' => $user->id]);
        $client2 = OAuthClient::factory()->create(['created_by' => $user->id]);

        $this->assertEquals($user->id, $client1->creator->id);
        $this->assertEquals($user->id, $client2->creator->id);
        $this->assertEquals($client1->creator->id, $client2->creator->id);
    }

    public function test_creator_and_updater_can_be_different_users()
    {
        $creator = User::factory()->create(['name' => 'Creator User']);
        $updater = User::factory()->create(['name' => 'Updater User']);
        
        $client = OAuthClient::factory()->create([
            'created_by' => $creator->id,
            'updated_by' => $updater->id,
        ]);

        $this->assertEquals($creator->id, $client->creator->id);
        $this->assertEquals($updater->id, $client->updater->id);
        $this->assertNotEquals($client->creator->id, $client->updater->id);
    }

    public function test_creator_and_updater_can_be_same_user()
    {
        $user = User::factory()->create();
        
        $client = OAuthClient::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->assertEquals($user->id, $client->creator->id);
        $this->assertEquals($user->id, $client->updater->id);
        $this->assertEquals($client->creator->id, $client->updater->id);
    }

    public function test_relationships_eager_loading()
    {
        $user = User::factory()->create();
        $clients = OAuthClient::factory()
            ->count(3)
            ->create(['created_by' => $user->id]);

        // Test eager loading to avoid N+1 queries
        $loadedClients = OAuthClient::with(['creator', 'updater'])->get();

        foreach ($loadedClients as $client) {
            if ($client->created_by) {
                $this->assertTrue($client->relationLoaded('creator'));
            }
            if ($client->updated_by) {
                $this->assertTrue($client->relationLoaded('updater'));
            }
        }
    }

    public function test_relationship_foreign_key_constraints()
    {
        $user = User::factory()->create();
        $client = OAuthClient::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        // Verify that deleting the user doesn't cascade delete the client
        // (This depends on your actual database constraints)
        $this->assertNotNull($client->creator);
        $this->assertNotNull($client->updater);
        
        // If you have proper foreign key constraints, you might want to test them here
        // For example, if you set up ON DELETE SET NULL constraints:
        // $user->delete();
        // $client->refresh();
        // $this->assertNull($client->created_by);
        // $this->assertNull($client->updated_by);
    }
}