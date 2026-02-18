<?php

namespace App\Integrations\Services;

class Wave extends AbstractOAuthService
{
    public function isAccountingIntegration(): bool
    {
        return true;
    }
}
