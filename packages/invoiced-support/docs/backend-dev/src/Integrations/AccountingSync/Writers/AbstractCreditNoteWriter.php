<?php

namespace App\Integrations\AccountingSync\Writers;

use App\Integrations\AccountingSync\Exceptions\SyncException;
use App\Integrations\AccountingSync\Interfaces\AccountingWritableModelInterface;
use App\Integrations\AccountingSync\Models\AccountingCreditNoteMapping;
use App\AccountsReceivable\Models\CreditNote;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\Enums\IntegrationType;
use Carbon\CarbonImmutable;
use App\Core\Orm\Event\ModelCreated;
use App\Core\Orm\Event\ModelUpdated;
use App\Core\Orm\Model;

abstract class AbstractCreditNoteWriter extends AbstractWriter
{
    /**
     * @throws SyncException
     */
    abstract protected function performCreate(CreditNote $creditNote, Model $account, AccountingSyncProfile $syncProfile): void;

    /**
     * @throws SyncException
     */
    abstract protected function performUpdate(CreditNote $creditNote, Model $account, AccountingSyncProfile $syncProfile, AccountingCreditNoteMapping $creditNoteMapping): void;

    /**
     * @throws SyncException
     */
    abstract protected function performVoid(CreditNote $creditNote, Model $account, AccountingSyncProfile $syncProfile, AccountingCreditNoteMapping $creditNoteMapping): void;

    public function isEnabled(AccountingSyncProfile $syncProfile): bool
    {
        return $syncProfile->write_credit_notes;
    }

    /**
     * Checks if record was created after sync profile's start date.
     */
    public function shouldReconcile(CreditNote $creditNote, AccountingSyncProfile $syncProfile): bool
    {
        return CarbonImmutable::createFromTimestamp($creditNote->date)
            ->greaterThanOrEqualTo($syncProfile->getStartDate());
    }

    public function create(AccountingWritableModelInterface $record, Model $account, AccountingSyncProfile $syncProfile): void
    {
        /** @var CreditNote $record */
        // do not write if credit note is voided
        if ($record->voided) {
            return;
        }

        // check record date
        if (!$this->shouldReconcile($record, $syncProfile)) {
            return;
        }

        // do not write if already created
        $integrationType = $syncProfile->getIntegrationType();
        if ($this->getCreditNoteMapping($record, $integrationType)) {
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
        /** @var CreditNote $record */
        // check record date
        if (!$this->shouldReconcile($record, $syncProfile)) {
            return;
        }

        $integrationType = $syncProfile->getIntegrationType();
        $creditNoteMapping = $this->getCreditNoteMapping($record, $integrationType);

        // create new credit note if a mapping doesn't exist
        if (!$creditNoteMapping) {
            $this->create($record, $account, $syncProfile);

            return;
        } elseif (AccountingCreditNoteMapping::SOURCE_ACCOUNTING_SYSTEM === $creditNoteMapping->source) {
            return; // Do not update credit notes that originated in the accounting system.
        }

        try {
            if ($record->voided) {
                $this->performVoid($record, $account, $syncProfile, $creditNoteMapping);
            } else {
                $this->performUpdate($record, $account, $syncProfile, $creditNoteMapping);
            }

            $this->updateCreditNoteMapping($creditNoteMapping);
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
     * Looks up a AccountingCreditNoteMapping using the given invoice.
     * Returns database results.
     */
    public function getCreditNoteMapping(CreditNote $creditNote, IntegrationType $integrationType): ?AccountingCreditNoteMapping
    {
        return AccountingCreditNoteMapping::findForCreditNote($creditNote, $integrationType);
    }

    /**
     * Creates and stores a new AccountingCreditNoteMapping using
     * the given credit note object, accountingId and source.
     */
    public function saveCreditNoteMapping(CreditNote $creditNote, IntegrationType $integrationType, string $accountingId, string $source): void
    {
        $mapping = new AccountingCreditNoteMapping();
        $mapping->source = $source;
        $mapping->setIntegration($integrationType);
        $mapping->credit_note = $creditNote;
        $mapping->accounting_id = $accountingId;
        $mapping->save();
    }

    /**
     * Updates a credit note mapping with the latest sync time.
     */
    private function updateCreditNoteMapping(AccountingCreditNoteMapping $mapping): void
    {
        $mapping->updated_at = time();
        $mapping->save();
    }
}
