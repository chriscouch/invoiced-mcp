<?php

namespace App\Integrations\AccountingSync\Interfaces;

use App\Integrations\AccountingSync\Exceptions\SyncException;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\ValueObjects\ReadQuery;
use App\Core\Orm\Model;

interface AccountingReaderInterface
{
    /**
     * Gets the identifier of this reader. The identifier must
     * be unique within the namespace of all readers.
     */
    public function getId(): string;

    /**
     * Whether reading is enabled for this reader type.
     */
    public function isEnabled(AccountingSyncProfile $syncProfile): bool;

    /**
     * Retrieves objects from the accounting system that match the query
     * and updates/creates them in Invoiced.
     *
     * @throws SyncException
     */
    public function syncAll(Model $account, AccountingSyncProfile $syncProfile, ReadQuery $query): void;

    /**
     * Retrieves an object by its id from the accounting system
     * and attempts to update/create it on Invoiced.
     *
     * @throws SyncException
     */
    public function syncOne(Model $account, AccountingSyncProfile $syncProfile, string $objectId): void;
}
