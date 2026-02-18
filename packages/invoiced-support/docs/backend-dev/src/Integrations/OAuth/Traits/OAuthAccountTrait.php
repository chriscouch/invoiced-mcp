<?php

namespace App\Integrations\OAuth\Traits;

trait OAuthAccountTrait
{
    public function persistOAuth(): void
    {
        $this->saveOrFail();
    }

    public function deleteOAuth(): void
    {
        $this->deleteOrFail();
    }
}
