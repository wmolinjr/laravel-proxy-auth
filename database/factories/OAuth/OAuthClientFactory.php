<?php

namespace Database\Factories\OAuth;

use App\Models\OAuth\OAuthClient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OAuthClientFactory extends Factory
{
    protected $model = OAuthClient::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'identifier' => $this->faker->unique()->word() . '-' . $this->faker->randomNumber(4),
            'name' => $this->faker->company() . ' App',
            'description' => $this->faker->sentence(),
            'secret' => Str::random(40),
            'redirect' => implode(',', [
                'https://example.com/callback',
                'https://app.example.com/auth/callback'
            ]),
            'redirect_uris' => [
                'https://example.com/callback',
                'https://app.example.com/auth/callback'
            ],
            'grants' => ['authorization_code', 'refresh_token'],
            'scopes' => ['openid', 'profile', 'email'],
            'is_confidential' => true,
            'personal_access_client' => false,
            'password_client' => false,
            'revoked' => false,
            'health_check_url' => $this->faker->url() . '/health',
            'health_check_interval' => $this->faker->randomElement([300, 600, 900, 1800]),
            'health_check_enabled' => $this->faker->boolean(70),
            'health_check_failures' => 0,
            'last_health_check' => $this->faker->optional()->dateTimeBetween('-1 day', 'now'),
            'health_status' => $this->faker->randomElement(['healthy', 'unhealthy', 'unknown', 'error']),
            'last_error_message' => null,
            'last_activity_at' => $this->faker->optional(80)->dateTimeBetween('-1 week', 'now'),
            'is_active' => true,
            'maintenance_mode' => false,
            'maintenance_message' => null,
            'environment' => $this->faker->randomElement(['production', 'staging', 'development']),
            'tags' => $this->faker->optional()->randomElements(['api', 'mobile', 'web', 'internal'], 2),
            'contact_email' => $this->faker->email(),
            'website_url' => $this->faker->optional()->url(),
            'max_concurrent_tokens' => $this->faker->randomElement([100, 500, 1000, 2000]),
            'rate_limit_per_minute' => $this->faker->randomElement([60, 100, 200, 500]),
            'version' => $this->faker->semver(),
            'created_by' => User::factory(),
            'updated_by' => null,
        ];
    }

    /**
     * Indicate that the client is confidential
     */
    public function confidential(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_confidential' => true,
            'secret' => Str::random(40),
        ]);
    }

    /**
     * Indicate that the client is public
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_confidential' => false,
            'secret' => null,
        ]);
    }

    /**
     * Indicate that the client is revoked
     */
    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'revoked' => true,
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the client is inactive
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the client is in maintenance mode
     */
    public function maintenance(): static
    {
        return $this->state(fn (array $attributes) => [
            'maintenance_mode' => true,
            'maintenance_message' => 'Scheduled maintenance in progress',
        ]);
    }

    /**
     * Indicate that the client is healthy
     */
    public function healthy(): static
    {
        return $this->state(fn (array $attributes) => [
            'health_status' => 'healthy',
            'health_check_enabled' => true,
            'last_health_check' => now(),
            'health_check_failures' => 0,
            'last_error_message' => null,
        ]);
    }

    /**
     * Indicate that the client is unhealthy
     */
    public function unhealthy(): static
    {
        return $this->state(fn (array $attributes) => [
            'health_status' => 'unhealthy',
            'health_check_enabled' => true,
            'last_health_check' => now(),
            'health_check_failures' => $this->faker->numberBetween(1, 5),
            'last_error_message' => 'Service unavailable',
        ]);
    }

    /**
     * Indicate that the client has health check errors
     */
    public function healthError(): static
    {
        return $this->state(fn (array $attributes) => [
            'health_status' => 'error',
            'health_check_enabled' => true,
            'last_health_check' => now(),
            'health_check_failures' => $this->faker->numberBetween(3, 10),
            'last_error_message' => 'Connection timeout',
        ]);
    }

    /**
     * Indicate that the client is for production environment
     */
    public function production(): static
    {
        return $this->state(fn (array $attributes) => [
            'environment' => 'production',
            'health_check_enabled' => true,
            'health_check_interval' => 300,
        ]);
    }

    /**
     * Indicate that the client is for staging environment
     */
    public function staging(): static
    {
        return $this->state(fn (array $attributes) => [
            'environment' => 'staging',
            'health_check_enabled' => true,
            'health_check_interval' => 600,
        ]);
    }

    /**
     * Indicate that the client is for development environment
     */
    public function development(): static
    {
        return $this->state(fn (array $attributes) => [
            'environment' => 'development',
            'health_check_enabled' => false,
            'health_check_interval' => 1800,
        ]);
    }

    /**
     * Configure the client with specific grants
     */
    public function withGrants(array $grants): static
    {
        return $this->state(fn (array $attributes) => [
            'grants' => $grants,
        ]);
    }

    /**
     * Configure the client with specific scopes
     */
    public function withScopes(array $scopes): static
    {
        return $this->state(fn (array $attributes) => [
            'scopes' => $scopes,
        ]);
    }

    /**
     * Configure the client with specific redirect URIs
     */
    public function withRedirectUris(array $uris): static
    {
        return $this->state(fn (array $attributes) => [
            'redirect_uris' => $uris,
            'redirect' => implode(',', $uris),
        ]);
    }

    /**
     * Configure the client with health check enabled
     */
    public function withHealthCheck(string $url = null, int $interval = 300): static
    {
        return $this->state(fn (array $attributes) => [
            'health_check_enabled' => true,
            'health_check_url' => $url ?? $this->faker->url() . '/health',
            'health_check_interval' => $interval,
        ]);
    }
}