<?php

namespace App\Integrations\AccountingSync\Writers;

use App\Integrations\AccountingSync\Interfaces\AccountingWritableModelInterface;
use App\Integrations\AccountingSync\Interfaces\AccountingWriterInterface;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Core\Orm\Model;

/**
 * A NoOp in case the user does not have
 * a supported accounting system.
 */
class NullWriter implements AccountingWriterInterface
{
    public function isEnabled(AccountingSyncProfile $syncProfile): bool
    {
        return false;
    }

    public function create(AccountingWritableModelInterface $record, Model $account, AccountingSyncProfile $syncProfile): void
    {
    }

    public function update(AccountingWritableModelInterface $record, Model $account, AccountingSyncProfile $syncProfile): void
    {
    }

    public function delete(AccountingWritableModelInterface $record, Model $account, AccountingSyncProfile $syncProfile): void
    {
    }
}
