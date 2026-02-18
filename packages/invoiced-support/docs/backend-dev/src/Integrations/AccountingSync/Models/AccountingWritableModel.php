<?php

namespace App\Integrations\AccountingSync\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Integrations\AccountingSync\Interfaces\AccountingWritableModelInterface;
use App\Integrations\AccountingSync\Traits\AccountingWritableModelTrait;

/**
 * Extends count of listeners
 * Class AccountingIntegrationModel.
 */
abstract class AccountingWritableModel extends MultitenantModel implements AccountingWritableModelInterface
{
    use AccountingWritableModelTrait;

    protected function initialize(): void
    {
        $this->initializeAccountingIntegration();
        parent::initialize();
    }
}
