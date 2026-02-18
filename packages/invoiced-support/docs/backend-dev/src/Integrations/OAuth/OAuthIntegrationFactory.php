<?php

namespace App\Integrations\OAuth;

use App\Integrations\Exceptions\OAuthException;
use App\Integrations\OAuth\Interfaces\OAuthIntegrationInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;

class OAuthIntegrationFactory
{
    public function __construct(private ServiceLocator $locator)
    {
    }

    /**
     * @throws OAuthException
     */
    public function get(string $id): OAuthIntegrationInterface
    {
        if (!$this->locator->has($id)) {
            throw new OAuthException('Not a supported OAuth integration: '.$id);
        }

        return $this->locator->get($id);
    }
}
