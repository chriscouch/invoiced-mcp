<?php

namespace App\Core\Authentication\OAuth\ValueObjects;

use League\OAuth2\Server\Entities\ScopeEntityInterface;

final class OAuthScope implements ScopeEntityInterface
{
    public function __construct(private string $identifier)
    {
    }

    public function jsonSerialize(): mixed
    {
        return $this->getIdentifier();
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }
}
