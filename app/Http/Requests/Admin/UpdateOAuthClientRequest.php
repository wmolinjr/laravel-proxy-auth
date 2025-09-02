<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOAuthClientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Add proper authorization logic here if needed
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
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
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The client name is required.',
            'redirect_uris.required' => 'At least one redirect URI is required.',
            'redirect_uris.min' => 'At least one redirect URI is required.',
            'redirect_uris.*.url' => 'Each redirect URI must be a valid URL.',
            'grants.required' => 'At least one grant type must be selected.',
            'grants.min' => 'At least one grant type must be selected.',
            'grants.*.in' => 'The selected grant type is invalid.',
            'scopes.required' => 'At least one scope must be selected.',
            'scopes.min' => 'At least one scope must be selected.',
            'environment.in' => 'The environment must be production, staging, or development.',
            'health_check_url.required_if' => 'Health check URL is required when health check is enabled.',
            'health_check_interval.min' => 'Health check interval must be at least 60 seconds.',
            'health_check_interval.max' => 'Health check interval must not exceed 3600 seconds (1 hour).',
            'max_concurrent_tokens.min' => 'Max concurrent tokens must be at least 1.',
            'max_concurrent_tokens.max' => 'Max concurrent tokens must not exceed 10,000.',
            'rate_limit_per_minute.min' => 'Rate limit must be at least 1 request per minute.',
            'rate_limit_per_minute.max' => 'Rate limit must not exceed 1,000 requests per minute.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'redirect_uris' => 'redirect URIs',
            'redirect_uris.*' => 'redirect URI',
            'grants' => 'grant types',
            'grants.*' => 'grant type',
            'scopes' => 'scopes',
            'scopes.*' => 'scope',
            'is_confidential' => 'client type',
            'health_check_enabled' => 'health check',
            'health_check_url' => 'health check URL',
            'health_check_interval' => 'health check interval',
            'contact_email' => 'contact email',
            'website_url' => 'website URL',
            'max_concurrent_tokens' => 'max concurrent tokens',
            'rate_limit_per_minute' => 'rate limit per minute',
        ];
    }
}