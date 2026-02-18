<?php

namespace App\Integrations\AccountingSync\Writers;

use App\AccountsReceivable\Models\Customer;
use App\Integrations\AccountingSync\Exceptions\SyncException;
use App\Integrations\AccountingSync\Interfaces\AccountingWritableModelInterface;
use App\Integrations\AccountingSync\Models\AccountingCustomerMapping;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\Enums\IntegrationType;
use App\Core\Orm\Event\ModelCreated;
use App\Core\Orm\Event\ModelUpdated;
use App\Core\Orm\Model;

abstract class AbstractCustomerWriter extends AbstractWriter
{
    /**
     * @throws SyncException
     */
    abstract protected function performCreate(Customer $customer, Model $account, AccountingSyncProfile $syncProfile): void;

    /**
     * @throws SyncException
     */
    abstract protected function performUpdate(AccountingCustomerMapping $mapping, Customer $customer, Model $account, AccountingSyncProfile $syncProfile): void;

    public function isEnabled(AccountingSyncProfile $syncProfile): bool
    {
        return $syncProfile->write_customers;
    }

    public function create(AccountingWritableModelInterface $record, Model $account, AccountingSyncProfile $syncProfile): void
    {
        $integrationType = $syncProfile->getIntegrationType();
        /** @var Customer $record */
        // do not write if already created
        if (AccountingCustomerMapping::findForCustomer($record, $integrationType)) {
            return;
        }

        try {
            $this->performCreate($record, $account, $syncProfile);
            $this->handleSyncSuccess($record, $syncProfile);
        } catch (SyncException $e) {
            $this->handleSyncException($record, $integrationType, $e->getMessage(), ModelCreated::getName());
        }
    }

    public function update(AccountingWritableModelInterface $record, Model $account, AccountingSyncProfile $syncProfile): void
    {
        $integrationType = $syncProfile->getIntegrationType();
        /** @var Customer $record */
        $mapping = AccountingCustomerMapping::findForCustomer($record, $integrationType);

        // create a new customer if a mapping doesn't exist
        if (!$mapping) {
            $this->create($record, $account, $syncProfile);

            return;
        } elseif (AccountingCustomerMapping::SOURCE_ACCOUNTING_SYSTEM === $mapping->source) {
            // Do not update customers that originated in the accounting system.
            return;
        }

        try {
            $this->performUpdate($mapping, $record, $account, $syncProfile);
            $this->updateCustomerMapping($mapping);
            $this->handleSyncSuccess($record, $syncProfile);
        } catch (SyncException $e) {
            $this->handleSyncException($record, $integrationType, $e->getMessage(), ModelUpdated::getName());
        }
    }

    public function delete(AccountingWritableModelInterface $record, Model $account, AccountingSyncProfile $syncProfile): void
    {
        // Deletes are not supported.
    }

    //
    // Mappings
    //

    /**
     * Creates and stores a new AccountingCustomerMapping using
     * the given customer object and accountingId.
     */
    public function saveCustomerMapping(Customer $customer, IntegrationType $integrationType, string $accountingId, string $source): void
    {
        $mapping = new AccountingCustomerMapping();
        $mapping->source = $source;
        $mapping->setIntegration($integrationType);
        $mapping->customer = $customer;
        $mapping->accounting_id = $accountingId;
        $mapping->saveOrFail();
    }

    /**
     * Updates a customer mapping with the latest sync time.
     */
    private function updateCustomerMapping(AccountingCustomerMapping $mapping): void
    {
        $mapping->updated_at = time();
        $mapping->save();
    }
}
