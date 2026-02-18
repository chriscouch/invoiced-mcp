<?php

namespace App\Integrations\AccountingSync\Writers;

use App\Integrations\AccountingSync\Exceptions\SyncException;
use App\Integrations\AccountingSync\Interfaces\AccountingWritableModelInterface;
use App\Integrations\AccountingSync\Models\AccountingInvoiceMapping;
use App\AccountsReceivable\Models\Invoice;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\Enums\IntegrationType;
use Carbon\CarbonImmutable;
use App\Core\Orm\Event\ModelCreated;
use App\Core\Orm\Event\ModelUpdated;
use App\Core\Orm\Model;

abstract class AbstractInvoiceWriter extends AbstractWriter
{
    /**
     * @throws SyncException
     */
    abstract protected function performCreate(Invoice $invoice, Model $account, AccountingSyncProfile $syncProfile): void;

    /**
     * @throws SyncException
     */
    abstract protected function performUpdate(Invoice $invoice, Model $account, AccountingSyncProfile $syncProfile, AccountingInvoiceMapping $invoiceMapping): void;

    /**
     * @throws SyncException
     */
    abstract protected function performVoid(Invoice $invoice, Model $account, AccountingSyncProfile $syncProfile, AccountingInvoiceMapping $invoiceMapping): void;

    public function isEnabled(AccountingSyncProfile $syncProfile): bool
    {
        return $syncProfile->write_invoices;
    }

    /**
     * Checks if record was created after sync profile's start date.
     */
    public function shouldReconcile(Invoice $invoice, AccountingSyncProfile $syncProfile): bool
    {
        return CarbonImmutable::createFromTimestamp($invoice->date)
            ->greaterThanOrEqualTo($syncProfile->getStartDate());
    }

    public function create(AccountingWritableModelInterface $record, Model $account, AccountingSyncProfile $syncProfile): void
    {
        /** @var Invoice $record */
        // do not write if invoice is voided
        if ($record->voided) {
            return;
        }

        // check record date
        if (!$this->shouldReconcile($record, $syncProfile)) {
            return;
        }

        // do not write if already created
        $integrationType = $syncProfile->getIntegrationType();
        if (AccountingInvoiceMapping::findForInvoice($record, $integrationType)) {
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
        /** @var Invoice $record */
        // check record date
        if (!$this->shouldReconcile($record, $syncProfile)) {
            return;
        }

        $integrationType = $syncProfile->getIntegrationType();
        $invoiceMapping = AccountingInvoiceMapping::findForInvoice($record, $integrationType);

        // create new invoice if a mapping doesn't exist
        if (!$invoiceMapping) {
            $this->create($record, $account, $syncProfile);

            return;
        } elseif (AccountingInvoiceMapping::SOURCE_ACCOUNTING_SYSTEM === $invoiceMapping->source) {
            return; // Do not update invoices that originated in the accounting system.
        }

        try {
            if ($record->voided) {
                $this->performVoid($record, $account, $syncProfile, $invoiceMapping);
            } else {
                $this->performUpdate($record, $account, $syncProfile, $invoiceMapping);
            }

            $this->updateInvoiceMapping($invoiceMapping);
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
     * Looks up a AccountingInvoiceMapping using the given invoice.
     * Returns database results.
     */
    public function getInvoiceMapping(Invoice $invoice, IntegrationType $integrationType): ?AccountingInvoiceMapping
    {
        return AccountingInvoiceMapping::findForInvoice($invoice, $integrationType);
    }

    /**
     * Creates and stores a new AccountingInvoiceMapping using
     * the given invoice object, accountingId and source.
     */
    public function saveInvoiceMapping(Invoice $invoice, IntegrationType $integrationType, string $accountingId, string $source): void
    {
        $mapping = new AccountingInvoiceMapping();
        $mapping->source = $source;
        $mapping->setIntegration($integrationType);
        $mapping->invoice = $invoice;
        $mapping->accounting_id = $accountingId;
        $mapping->save();
    }

    /**
     * Updates an invoice mapping with the latest sync time.
     */
    private function updateInvoiceMapping(AccountingInvoiceMapping $mapping): void
    {
        $mapping->updated_at = time();
        $mapping->save();
    }
}
