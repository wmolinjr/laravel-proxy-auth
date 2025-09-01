<?php

namespace App\Repositories\OAuth;

use App\Entities\OAuth\ClientEntity;
use App\Models\OAuth\OAuthClient;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;

class ClientRepository implements ClientRepositoryInterface
{
    /**
     * Get a client by identifier
     */
    public function getClientEntity(string $clientIdentifier): ?ClientEntityInterface
    {
        $client = OAuthClient::active()->find($clientIdentifier);
        
        if (!$client) {
            return null;
        }

        return new ClientEntity(
            $client->id,
            $client->name,
            $client->getRedirectUris()
        );
    }

    /**
     * Validate a client
     */
    public function validateClient(string $clientIdentifier, ?string $clientSecret, ?string $grantType): bool
    {
        $client = OAuthClient::active()->find($clientIdentifier);

        if (!$client) {
            return false;
        }

        // Para authorization code grant, validar secret se fornecido
        if ($grantType === 'authorization_code' && $clientSecret !== null) {
            return hash_equals($client->secret, $clientSecret);
        }

        // Para refresh token grant, sempre validar secret
        if ($grantType === 'refresh_token') {
            return hash_equals($client->secret ?? '', $clientSecret ?? '');
        }

        return true;
    }
}