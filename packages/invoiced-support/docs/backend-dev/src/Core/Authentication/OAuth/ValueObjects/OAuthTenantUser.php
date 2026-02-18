<?php

namespace App\Core\Authentication\OAuth\ValueObjects;

use App\Companies\Models\Company;
use League\OAuth2\Server\Entities\UserEntityInterface;

final class OAuthTenantUser implements UserEntityInterface
{
    public function __construct(private Company $company)
    {
    }

    public function getIdentifier(): string
    {
        return 'tenant:'.$this->company->id();
    }
}
