<?php

namespace App\Integrations\AccountingSync\Interfaces;

use App\Integrations\AccountingSync\Exceptions\ExtractException;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\ValueObjects\ReadQuery;
use Generator;
use App\Core\Orm\Model;

/**
 * Extracts data from the accounting system.
 */
interface ExtractorInterface
{
    /**
     * Configures the extractor for a given account. This method
     * is stateful. If the application context is changing
     * then this must be called again with the new account.
     *
     * @throws ExtractException
     */
    public function initialize(Model $account, AccountingSyncProfile $syncProfile): void;

    /**
     * Gets a single object by its ID from the accounting system.
     *
     * @throws ExtractException
     */
    public function getObject(string $objectId): AccountingRecordInterface;

    /**
     * Returns a list of objects from the accounting system that match
     * the given query.
     *
     * @throws ExtractException
     *
     * @return Generator<AccountingRecordInterface>
     */
    public function getObjects(AccountingSyncProfile $syncProfile, ReadQuery $query): Generator;

    /**
     * Gets the ID from an accounting system object.
     */
    public function getObjectId(AccountingRecordInterface $accountingObject): string;
}
