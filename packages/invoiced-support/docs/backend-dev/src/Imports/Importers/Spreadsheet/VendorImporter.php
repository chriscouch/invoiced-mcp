<?php

namespace App\Imports\Importers\Spreadsheet;

use App\AccountsPayable\Models\Vendor;
use App\Core\Orm\Model;
use App\Imports\Exceptions\RecordException;
use App\Imports\Libs\ImportHelper;
use App\Imports\Models\Import;
use App\Imports\Traits\DeleteOperationTrait;
use App\Imports\ValueObjects\ImportRecordResult;

/**
 * Imports vendors from a spreadsheet.
 */
class VendorImporter extends BaseSpreadsheetImporter
{
    use DeleteOperationTrait;

    public function buildRecord(array $mapping, array $line, array $options, Import $import): array
    {
        $record = parent::buildRecord($mapping, $line, $options, $import);

        // clear out an empty account number
        if (isset($record['number']) && !$record['number']) {
            unset($record['number']);
        }

        // convert country long form name to abbreviation (i.e. United States -> US)
        if (isset($record['country'])) {
            $record['country'] = ImportHelper::parseCountry($record['country']);
        }

        // ignore active status if null
        if (array_key_exists('active', $record) && null === $record['active']) {
            unset($record['active']);
        }

        return $record;
    }

    //
    // Operations
    //

    protected function findExistingRecord(array $record): ?Model
    {
        // Vendors are identified by account #, when provided.
        // If not provided then vendors are identified by name.
        if ($accountNumber = array_value($record, 'number')) {
            return Vendor::where('number', $accountNumber)->oneOrNull();
        }

        return Vendor::where('name', array_value($record, 'name'))->oneOrNull();
    }

    protected function createRecord(array $record): ImportRecordResult
    {
        $vendor = new Vendor();

        if (!$vendor->create($record)) {
            // grab error messages, if creating vendor fails
            throw new RecordException('Could not create vendor: '.$vendor->getErrors());
        }

        return new ImportRecordResult($vendor, ImportRecordResult::CREATE);
    }

    /**
     * @param Vendor $existingRecord
     */
    protected function updateRecord(array $record, Model $existingRecord): ImportRecordResult
    {
        foreach ($record as $k => $v) {
            $existingRecord->$k = $v;
        }

        if (!$existingRecord->save()) {
            // grab error messages, if updating vendor fails
            throw new RecordException('Could not update vendor: '.$existingRecord->getErrors());
        }

        return new ImportRecordResult($existingRecord, ImportRecordResult::UPDATE);
    }
}
