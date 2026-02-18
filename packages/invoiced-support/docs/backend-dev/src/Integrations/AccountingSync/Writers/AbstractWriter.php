<?php

namespace App\Integrations\AccountingSync\Writers;

use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Integrations\AccountingSync\Interfaces\AccountingWritableModelInterface;
use App\Integrations\AccountingSync\Interfaces\AccountingWriterInterface;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\Models\ReconciliationError;
use App\Integrations\Enums\IntegrationType;

abstract class AbstractWriter implements AccountingWriterInterface, StatsdAwareInterface
{
    use StatsdAwareTrait;

    /**
     * Handles a successful posted payment.
     */
    protected function handleSyncSuccess(AccountingWritableModelInterface $record, AccountingSyncProfile $syncProfile): void
    {
        // delete any previous reconciliation errors for this transaction
        /* @phpstan-ignore-next-line */
        ReconciliationError::where('object', $record->object)
            ->where('object_id', $record)
            ->delete();

        // update the last synced timestamp on the sync profile
        if ($syncProfile->last_synced < time()) {
            $syncProfile->last_synced = time();
            $syncProfile->save();
        }

        $this->statsd->increment('accounting_sync.write_succeeded', 1.0, ['integration' => $syncProfile->getIntegrationType()->toString()]);
    }

    /**
     * Handles an exception during the payment posting process.
     */
    protected function handleSyncException(AccountingWritableModelInterface $record, IntegrationType $integrationType, string $message, string $eventName, string $level = ReconciliationError::LEVEL_ERROR): void
    {
        ReconciliationError::makeWriteError(
            $integrationType->value,
            $record->getAccountingObjectReference(),
            $message,
            $eventName,
            $level
        );

        $this->statsd->increment('accounting_sync.write_failed', 1.0, ['integration' => $integrationType->toString()]);
    }

    protected function stripQuotes(string $input): string
    {
        return str_replace('"', "'", $input);
    }
}
