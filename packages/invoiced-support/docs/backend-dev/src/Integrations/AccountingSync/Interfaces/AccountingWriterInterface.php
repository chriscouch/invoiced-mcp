<?php

namespace App\Integrations\AccountingSync\Interfaces;

use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Core\Orm\Model;

/**
 * Writes Invoiced data into the accounting system.
 */
interface AccountingWriterInterface
{
    /**
     * Checks if the writer is enabled.
     */
    public function isEnabled(AccountingSyncProfile $syncProfile): bool;

    /**
     * Called when a model is created and should be written to the accounting system.
     */
    public function create(AccountingWritableModelInterface $record, Model $account, AccountingSyncProfile $syncProfile): void;

    /**
     * Called when a model is updated and should be written to the accounting system.
     */
    public function update(AccountingWritableModelInterface $record, Model $account, AccountingSyncProfile $syncProfile): void;

    /**
     * Called when a model is deleted and should be written to the accounting system.
     */
    public function delete(AccountingWritableModelInterface $record, Model $account, AccountingSyncProfile $syncProfile): void;
}
