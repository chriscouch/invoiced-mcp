<?php

namespace App\Integrations\AccountingSync\Traits;

use App\Core\Orm\Model;
use App\Imports\ValueObjects\ImportRecordResult;
use App\Integrations\AccountingSync\Exceptions\LoadException;
use App\Integrations\AccountingSync\ValueObjects\AbstractAccountingRecord;
use App\Integrations\Enums\IntegrationType;

trait AccountingLoaderTrait
{
    /**
     * @template T of Model
     *
     * @param T $model
     *
     * @return importRecordResult<T>
     *                               Generates a result object for a successful load that created a model
     */
    protected function makeCreateResult(AbstractAccountingRecord $record, Model $model): ImportRecordResult
    {
        $this->recordSuccess($record->integration);

        return new ImportRecordResult($model, ImportRecordResult::CREATE);
    }

    /**
     * Generates a result object for a successful load that updated a model.
     */
    protected function makeUpdateResult(AbstractAccountingRecord $record, Model $model): ImportRecordResult
    {
        $this->recordSuccess($record->integration);

        return new ImportRecordResult($model, ImportRecordResult::UPDATE);
    }

    /**
     * Generates a result object for a successful load that voided a model.
     */
    protected function makeVoidResult(AbstractAccountingRecord $record, Model $model): ImportRecordResult
    {
        $this->recordSuccess($record->integration);

        return new ImportRecordResult($model, ImportRecordResult::VOID);
    }

    /**
     * Generates a result object for a successful load that deleted a model.
     */
    protected function makeDeleteResult(AbstractAccountingRecord $record, Model $model): ImportRecordResult
    {
        $this->recordSuccess($record->integration);

        return new ImportRecordResult($model, ImportRecordResult::DELETE);
    }

    private function recordSuccess(IntegrationType $integrationType): void
    {
        $this->statsd->increment('accounting_sync.read_succeeded', 1.0, ['integration' => $integrationType->toString()]);
    }

    /**
     * Generates an exception object for a failed load.
     */
    protected function makeException(AbstractAccountingRecord $record, string $msg): LoadException
    {
        $this->recordFailure($record->integration);

        return new LoadException($msg);
    }

    private function recordFailure(IntegrationType $integrationType): void
    {
        $this->statsd->increment('accounting_sync.read_failed', 1.0, ['integration' => $integrationType->toString()]);
    }
}
