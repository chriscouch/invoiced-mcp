<?php

namespace App\Integrations\AccountingSync\Interfaces;

use App\Integrations\AccountingSync\Exceptions\TransformException;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\ValueObjects\AbstractAccountingRecord;
use App\Core\Orm\Model;

/**
 * Transforms records extracted from the accounting system
 * in preparation for loading into Invoiced.
 */
interface TransformerInterface
{
    /**
     * Configures the transformer for a given account. This method
     * is stateful. If the application context is changing
     * then this must be called again with the new account.
     *
     * @throws TransformException
     */
    public function initialize(Model $account, AccountingSyncProfile $syncProfile): void;

    /**
     * Transforms a record extracted from the accounting system
     * into a value object that can be loaded into Invoiced.
     *
     * @throws TransformException when the record cannot be transformed
     */
    public function transform(AccountingRecordInterface $input): ?AbstractAccountingRecord;
}
