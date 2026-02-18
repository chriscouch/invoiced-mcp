<?php

namespace App\Integrations\Services;

use App\Companies\Models\Company;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Interfaces\IntegrationInterface;

abstract class AbstractService implements IntegrationInterface
{
    protected bool $accountLoaded = false;

    public function __construct(
        protected Company $company,
        protected IntegrationType $integrationType,
    ) {
    }

    public function isAccountingIntegration(): bool
    {
        return false;
    }
}
