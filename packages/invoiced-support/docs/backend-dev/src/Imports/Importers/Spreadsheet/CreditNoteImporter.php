<?php

namespace App\Imports\Importers\Spreadsheet;

use App\AccountsReceivable\Models\CreditNote;
use App\Core\Orm\Model;
use App\Imports\Models\Import;
use App\Imports\Traits\ImportAccountingParametersTrait;
use App\Imports\ValueObjects\ImportRecordResult;

class CreditNoteImporter extends ReceivableDocumentImporter
{
    use ImportAccountingParametersTrait;

    protected function getDocumentClass(): string
    {
        return CreditNote::class;
    }

    protected function hasShippingParameters(): bool
    {
        // Credit notes do not have shipping information
        return false;
    }

    public function buildRecord(array $mapping, array $line, array $options, Import $import): array
    {
        $record = parent::buildRecord($mapping, $line, $options, $import);

        return $this->buildRecordAccounting($record);
    }

    protected function createRecord(array $record): ImportRecordResult
    {
        // pull out mapping fields
        $accountingSystem = $record['accounting_system'] ?? null;
        $accountingId = $record['accounting_id'] ?? null;
        unset($record['accounting_system']);
        unset($record['accounting_id']);

        $result = parent::createRecord($record);

        $creditNote = $result->getModel();
        if ($creditNote instanceof CreditNote) {
            // save accounting mapping
            if ($accountingSystem && $accountingId) {
                $this->saveAccountingMapping($creditNote, $accountingSystem, $accountingId);
            }
        }

        return $result;
    }

    /**
     * @param CreditNote $existingRecord
     */
    protected function updateRecord(array $record, Model $existingRecord): ImportRecordResult
    {
        // pull out mapping fields
        $accountingSystem = $record['accounting_system'] ?? null;
        $accountingId = $record['accounting_id'] ?? null;
        unset($record['accounting_system']);
        unset($record['accounting_id']);

        $result = parent::updateRecord($record, $existingRecord);

        // save accounting mapping
        if ($accountingSystem && $accountingId) {
            $this->saveAccountingMapping($existingRecord, $accountingSystem, $accountingId);
        }

        return $result;
    }
}
