<?php

namespace App\Repositories\OAuth;

use App\Entities\OAuth\UserEntity;
use App\Models\User;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;

class UserRepository implements UserRepositoryInterface
{
    /**
     * Get user entity by user credentials (for password grant)
     * Not used in authorization code flow, but required by interface
     */
    public function getUserEntityByUserCredentials(
        string $username,
        string $password,
        string $grantType,
        ClientEntityInterface $clientEntity
    ): ?UserEntityInterface {
        // Este método não é usado no authorization code flow
        // Mantido para compatibilidade com a interface
        return null;
    }

    /**
     * Get user entity by identifier
     */
    public function getUserEntityByIdentifier(string $identifier): ?UserEntityInterface
    {
        $user = User::find($identifier);
        
        if (!$user) {
            return null;
        }

        return new UserEntity($user->id);
    }
}