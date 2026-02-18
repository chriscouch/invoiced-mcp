<?php

namespace App\Imports\Traits;

use App\Core\Orm\Model;
use App\Imports\Exceptions\RecordException;
use App\Imports\ValueObjects\ImportRecordResult;

trait DeleteOperationTrait
{
    protected function deleteRecord(array $record, Model $existingRecord): ImportRecordResult
    {
        if (!$existingRecord->delete()) {
            throw new RecordException('Could not delete record: '.$existingRecord->getErrors());
        }

        return new ImportRecordResult($existingRecord, ImportRecordResult::DELETE);
    }
}
