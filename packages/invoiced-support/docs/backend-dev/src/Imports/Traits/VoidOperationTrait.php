<?php

namespace App\Imports\Traits;

use App\Core\Orm\Exception\ModelException;
use App\Core\Orm\Model;
use App\Imports\Exceptions\RecordException;
use App\Imports\ValueObjects\ImportRecordResult;

trait VoidOperationTrait
{
    protected function voidRecord(array $record, Model $existingRecord): ImportRecordResult
    {
        if (!method_exists($existingRecord, 'void')) {
            throw new RecordException('Voids not supported');
        }

        try {
            $existingRecord->void();

            return new ImportRecordResult($existingRecord, ImportRecordResult::VOID);
        } catch (ModelException $e) {
            throw new RecordException('Could not void record: '.$e->getMessage());
        }
    }
}
