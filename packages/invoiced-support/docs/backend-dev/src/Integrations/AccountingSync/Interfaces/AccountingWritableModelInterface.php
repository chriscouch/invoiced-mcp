<?php

namespace App\Integrations\AccountingSync\Interfaces;

use App\Companies\Models\Company;
use App\Integrations\AccountingSync\ValueObjects\InvoicedObjectReference;

/**
 * Interface AccountingWritableModelInterface.
 *
 * @phpstan-template T
 */
interface AccountingWritableModelInterface
{
    /**
     * Checks if we should write the model to the accounting system.
     */
    public function isReconcilable(): bool;

    /**
     * Skips writing the model to the accounting system.
     */
    public function skipReconciliation(): void;

    /**
     * all models we use must be multiTenant models.
     */
    public function tenant(): Company;

    /**
     * Gets the object reference for generating errors.
     */
    public function getAccountingObjectReference(): InvoicedObjectReference;
}
