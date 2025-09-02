<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\Admin\StoreOAuthClientRequest;
use App\Http\Requests\Admin\UpdateOAuthClientRequest;
use App\Models\OAuth\OAuthClient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class OAuthClientValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_oauth_client_validation_rules()
    {
        $request = new StoreOAuthClientRequest();
        $rules = $request->rules();

        $expectedRules = [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'redirect_uris' => ['required', 'array', 'min:1'],
            'redirect_uris.*' => ['required', 'url', 'max:500'],
            'grants' => ['required', 'array', 'min:1'],
            'grants.*' => ['required', 'string', 'in:authorization_code,client_credentials,refresh_token,password'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['required', 'string', 'max:100'],
            'is_confidential' => ['required', 'boolean'],
            'environment' => ['required', 'string', 'in:production,staging,development'],
            'health_check_enabled' => ['required', 'boolean'],
            'health_check_url' => ['nullable', 'required_if:health_check_enabled,true', 'url', 'max:500'],
            'health_check_interval' => ['nullable', 'integer', 'min:60', 'max:3600'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'website_url' => ['nullable', 'url', 'max:500'],
            'max_concurrent_tokens' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'rate_limit_per_minute' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ];

        foreach ($expectedRules as $field => $expectedRule) {
            $this->assertArrayHasKey($field, $rules);
            $this->assertEquals($expectedRule, $rules[$field]);
        }
    }

    public function test_update_oauth_client_validation_rules()
    {
        $request = new UpdateOAuthClientRequest();
        $rules = $request->rules();

        $expectedRules = [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'redirect_uris' => ['required', 'array', 'min:1'],
            'redirect_uris.*' => ['required', 'url', 'max:500'],
            'grants' => ['required', 'array', 'min:1'],
            'grants.*' => ['required', 'string', 'in:authorization_code,client_credentials,refresh_token,password'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['required', 'string', 'max:100'],
            'is_confidential' => ['required', 'boolean'],
            'environment' => ['required', 'string', 'in:production,staging,development'],
            'health_check_enabled' => ['required', 'boolean'],
            'health_check_url' => ['nullable', 'required_if:health_check_enabled,true', 'url', 'max:500'],
            'health_check_interval' => ['nullable', 'integer', 'min:60', 'max:3600'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'website_url' => ['nullable', 'url', 'max:500'],
            'max_concurrent_tokens' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'rate_limit_per_minute' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ];

        foreach ($expectedRules as $field => $expectedRule) {
            $this->assertArrayHasKey($field, $rules);
            $this->assertEquals($expectedRule, $rules[$field]);
        }
    }

    public function test_store_validation_passes_with_valid_data()
    {
        $validData = [
            'name' => 'Test Application',
            'description' => 'A test application for OAuth',
            'redirect_uris' => ['https://example.com/callback'],
            'grants' => ['authorization_code', 'refresh_token'],
            'scopes' => ['openid', 'profile'],
            'is_confidential' => true,
            'environment' => 'development',
            'health_check_enabled' => true,
            'health_check_url' => 'https://example.com/health',
            'health_check_interval' => 300,
            'contact_email' => 'admin@example.com',
            'website_url' => 'https://example.com',
            'max_concurrent_tokens' => 1000,
            'rate_limit_per_minute' => 100,
        ];

        $request = new StoreOAuthClientRequest();
        $validator = Validator::make($validData, $request->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_store_validation_fails_with_missing_required_fields()
    {
        $invalidData = [];

        $request = new StoreOAuthClientRequest();
        $validator = Validator::make($invalidData, $request->rules());

        $this->assertFalse($validator->passes());
        $this->assertTrue($validator->errors()->has('name'));
        $this->assertTrue($validator->errors()->has('redirect_uris'));
        $this->assertTrue($validator->errors()->has('grants'));
        $this->assertTrue($validator->errors()->has('scopes'));
        $this->assertTrue($validator->errors()->has('is_confidential'));
        $this->assertTrue($validator->errors()->has('environment'));
        $this->assertTrue($validator->errors()->has('health_check_enabled'));
    }

    public function test_store_validation_fails_with_invalid_name()
    {
        $testCases = [
            ['name' => ''], // empty
            ['name' => str_repeat('a', 256)], // too long
            ['name' => null], // null
        ];

        $request = new StoreOAuthClientRequest();

        foreach ($testCases as $testCase) {
            $data = array_merge($this->getValidStoreData(), $testCase);
            $validator = Validator::make($data, $request->rules());

            $this->assertFalse($validator->passes(), 'Validation should fail for name: ' . json_encode($testCase));
            $this->assertTrue($validator->errors()->has('name'));
        }
    }

    public function test_store_validation_fails_with_invalid_redirect_uris()
    {
        $testCases = [
            ['redirect_uris' => []], // empty array
            ['redirect_uris' => ['invalid-url']], // invalid URL
            ['redirect_uris' => ['https://example.com/' . str_repeat('a', 500)]], // URL too long
            ['redirect_uris' => [null]], // null in array
        ];

        $request = new StoreOAuthClientRequest();

        foreach ($testCases as $testCase) {
            $data = array_merge($this->getValidStoreData(), $testCase);
            $validator = Validator::make($data, $request->rules());

            $this->assertFalse($validator->passes(), 'Validation should fail for redirect_uris: ' . json_encode($testCase));
            $this->assertTrue($validator->errors()->has('redirect_uris') || $validator->errors()->has('redirect_uris.0'));
        }
    }

    public function test_store_validation_fails_with_invalid_grants()
    {
        $testCases = [
            ['grants' => []], // empty array
            ['grants' => ['invalid_grant']], // invalid grant type
            ['grants' => ['']], // empty string
        ];

        $request = new StoreOAuthClientRequest();

        foreach ($testCases as $testCase) {
            $data = array_merge($this->getValidStoreData(), $testCase);
            $validator = Validator::make($data, $request->rules());

            $this->assertFalse($validator->passes(), 'Validation should fail for grants: ' . json_encode($testCase));
            $this->assertTrue($validator->errors()->has('grants') || $validator->errors()->has('grants.0'));
        }
    }

    public function test_store_validation_fails_with_invalid_environment()
    {
        $testCases = [
            ['environment' => 'invalid'], // invalid environment
            ['environment' => ''], // empty string
            ['environment' => null], // null
        ];

        $request = new StoreOAuthClientRequest();

        foreach ($testCases as $testCase) {
            $data = array_merge($this->getValidStoreData(), $testCase);
            $validator = Validator::make($data, $request->rules());

            $this->assertFalse($validator->passes(), 'Validation should fail for environment: ' . json_encode($testCase));
            $this->assertTrue($validator->errors()->has('environment'));
        }
    }

    public function test_store_validation_requires_health_check_url_when_enabled()
    {
        $data = array_merge($this->getValidStoreData(), [
            'health_check_enabled' => true,
            'health_check_url' => null,
        ]);

        $request = new StoreOAuthClientRequest();
        $validator = Validator::make($data, $request->rules());

        $this->assertFalse($validator->passes());
        $this->assertTrue($validator->errors()->has('health_check_url'));
    }

    public function test_store_validation_allows_null_health_check_url_when_disabled()
    {
        $data = array_merge($this->getValidStoreData(), [
            'health_check_enabled' => false,
            'health_check_url' => null,
        ]);

        $request = new StoreOAuthClientRequest();
        $validator = Validator::make($data, $request->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_store_validation_fails_with_invalid_email()
    {
        $testCases = [
            ['contact_email' => 'invalid-email'], // invalid format
            ['contact_email' => str_repeat('a', 250) . '@example.com'], // too long
        ];

        $request = new StoreOAuthClientRequest();

        foreach ($testCases as $testCase) {
            $data = array_merge($this->getValidStoreData(), $testCase);
            $validator = Validator::make($data, $request->rules());

            $this->assertFalse($validator->passes(), 'Validation should fail for contact_email: ' . json_encode($testCase));
            $this->assertTrue($validator->errors()->has('contact_email'));
        }
    }

    public function test_store_validation_fails_with_invalid_numeric_fields()
    {
        $testCases = [
            ['health_check_interval' => 59], // too small
            ['health_check_interval' => 3601], // too large
            ['max_concurrent_tokens' => 0], // too small
            ['max_concurrent_tokens' => 10001], // too large
            ['rate_limit_per_minute' => 0], // too small
            ['rate_limit_per_minute' => 1001], // too large
        ];

        $request = new StoreOAuthClientRequest();

        foreach ($testCases as $testCase) {
            $data = array_merge($this->getValidStoreData(), $testCase);
            $validator = Validator::make($data, $request->rules());
            $field = array_key_first($testCase);

            $this->assertFalse($validator->passes(), "Validation should fail for {$field}: " . json_encode($testCase));
            $this->assertTrue($validator->errors()->has($field));
        }
    }

    public function test_update_validation_works_similarly_to_store()
    {
        $validData = $this->getValidStoreData();

        $updateRequest = new UpdateOAuthClientRequest();
        $validator = Validator::make($validData, $updateRequest->rules());

        $this->assertTrue($validator->passes());
    }

    private function getValidStoreData(): array
    {
        return [
            'name' => 'Test Application',
            'description' => 'A test application for OAuth',
            'redirect_uris' => ['https://example.com/callback'],
            'grants' => ['authorization_code', 'refresh_token'],
            'scopes' => ['openid', 'profile'],
            'is_confidential' => true,
            'environment' => 'development',
            'health_check_enabled' => false,
            'health_check_url' => null,
            'health_check_interval' => 300,
            'contact_email' => 'admin@example.com',
            'website_url' => 'https://example.com',
            'max_concurrent_tokens' => 1000,
            'rate_limit_per_minute' => 100,
        ];
    }
}