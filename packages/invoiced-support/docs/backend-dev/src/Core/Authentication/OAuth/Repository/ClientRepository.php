<?php

namespace App\Core\Authentication\OAuth\Repository;

use App\Core\Authentication\OAuth\Models\OAuthApplication;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;

class ClientRepository implements ClientRepositoryInterface
{
    public function getClientEntity($clientIdentifier): ?ClientEntityInterface
    {
        return OAuthApplication::where('identifier', $clientIdentifier)->oneOrNull();
    }

    public function validateClient($clientIdentifier, $clientSecret, $grantType): bool
    {
        if (!in_array($grantType, ['authorization_code', 'refresh_token'])) {
            return false;
        }

        $application = $this->getClientEntity($clientIdentifier);
        if (!$application instanceof OAuthApplication) {
            return false;
        }

        return hash_equals($application->secret, (string) $clientSecret);
    }
}
