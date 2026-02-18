<?php

namespace App\Imports\Importers\Spreadsheet;

use App\AccountsReceivable\Models\PricingObject;
use App\Core\Orm\Model;
use App\Imports\Exceptions\RecordException;
use App\Imports\Traits\DeleteOperationTrait;
use App\Imports\ValueObjects\ImportRecordResult;

abstract class PricingObjectImporter extends BaseSpreadsheetImporter
{
    use DeleteOperationTrait;

    abstract protected function getModelClass(): string;

    protected function findExistingRecord(array $record): ?Model
    {
        $class = $this->getModelClass();
        $id = trim((string) array_value($record, 'id'));

        return $class::getCurrent($id);
    }

    protected function createRecord(array $record): ImportRecordResult
    {
        $class = $this->getModelClass();
        /** @var PricingObject $object */
        $object = new $class();
        if (!$object->create($record)) {
            throw new RecordException('Could not create record: '.$object->getErrors());
        }

        return new ImportRecordResult($object, ImportRecordResult::CREATE);
    }

    /**
     * @param PricingObject $existingRecord
     */
    protected function updateRecord(array $record, Model $existingRecord): ImportRecordResult
    {
        // delete the old object
        if (!$existingRecord->delete()) {
            throw new RecordException('Could not delete record: '.$existingRecord->getErrors());
        }

        // create a new object
        $class = $this->getModelClass();
        /** @var PricingObject $newObject */
        $newObject = new $class();
        foreach ($record as $k => $v) {
            $newObject->$k = $v;
        }

        if (!$newObject->save()) {
            throw new RecordException('Could not update record: '.$newObject->getErrors());
        }

        return new ImportRecordResult($newObject, ImportRecordResult::UPDATE);
    }
}
