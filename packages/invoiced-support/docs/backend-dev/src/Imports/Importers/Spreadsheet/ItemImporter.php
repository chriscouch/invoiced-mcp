<?php

namespace App\Imports\Importers\Spreadsheet;

use App\AccountsReceivable\Models\Item;
use App\Core\Orm\Model;
use App\Imports\Models\Import;
use App\Imports\Traits\ImportAccountingParametersTrait;
use App\Imports\ValueObjects\ImportRecordResult;

class ItemImporter extends PricingObjectImporter
{
    use ImportAccountingParametersTrait;

    public function buildRecord(array $mapping, array $line, array $options, Import $import): array
    {
        $record = parent::buildRecord($mapping, $line, $options, $import);

        $record = $this->buildRecordAccounting($record);

        // sanitize type
        if (isset($record['type'])) {
            $record['type'] = strtolower(trim($record['type']));
        }

        return $record;
    }

    protected function createRecord(array $record): ImportRecordResult
    {
        // pull out mapping fields
        $accountingSystem = $record['accounting_system'] ?? null;
        $accountingId = $record['accounting_id'] ?? null;
        unset($record['accounting_system']);
        unset($record['accounting_id']);

        $result = parent::createRecord($record);

        $item = $result->getModel();
        if ($item instanceof Item) {
            // save accounting mapping
            if ($accountingSystem && $accountingId) {
                $this->saveAccountingMapping($item, $accountingSystem, $accountingId);
            }
        }

        return $result;
    }

    /**
     * @param Item $existingRecord
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

    protected function getModelClass(): string
    {
        return Item::class;
    }
}
