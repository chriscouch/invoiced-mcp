<?php

namespace App\Imports\Importers;

use App\Core\Database\TransactionManager;
use App\Imports\Exceptions\RecordException;
use App\Imports\Models\Import;
use App\Imports\Models\ImportedObject;
use App\Imports\ValueObjects\ImportRecordResult;
use App\Imports\ValueObjects\ImportResult;
use Throwable;

abstract class BaseFileImporter extends BaseImporter
{
    public function __construct(private TransactionManager $transactionManager)
    {
    }

    public function run(array $records, array $options, Import $import): ImportResult
    {
        $created = 0;
        $updated = 0;
        $failed = 0;
        $failures = [];
        $objects = [];

        try {
            foreach ($records as $record) {
                $import->incrementPosition();

                try {
                    $result = $this->transactionManager->perform(function () use ($record, $options) {
                        return $this->importRecord($record, $options);
                    });

                    if ($result->hadChange() && $model = $result->getModel()) {
                        $objects[] = ImportedObject::fromModel($model, $import->id);
                    }

                    if ($result->wasCreated()) {
                        ++$created;
                    } elseif ($result->wasUpdated() || $result->wasDeleted()) {
                        ++$updated;
                    }
                } catch (RecordException $e) {
                    ++$failed;
                    $failures[] = [
                        'data' => $record,
                        'reason' => $e->getMessage(),
                    ];
                }
            }
        } catch (Throwable $e) {
            ++$failed;
            $failures[] = [
                'reason' => 'Internal Server Error',
            ];
            if (isset($this->logger)) {
                $this->logger->error('Uncaught exception in importer', ['exception' => $e]);
            }
        }

        return new ImportResult($created, $updated, $failed, $failures, $objects);
    }

    /**
     * Imports a record.
     *
     * @throws RecordException when the import fails
     */
    abstract public function importRecord(array $record, array $options): ImportRecordResult;
}
