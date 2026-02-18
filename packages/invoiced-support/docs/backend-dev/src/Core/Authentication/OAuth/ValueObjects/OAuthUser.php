<?php

namespace App\Core\Authentication\OAuth\ValueObjects;

use App\Core\Authentication\Models\User;
use League\OAuth2\Server\Entities\UserEntityInterface;

final class OAuthUser implements UserEntityInterface
{
    public function __construct(private User $user)
    {
    }

    public function getIdentifier(): string
    {
        return 'user:'.$this->user->id();
    }
}
