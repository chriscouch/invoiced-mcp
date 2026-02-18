<?php

namespace App\Integrations\AccountingSync;

use App\Core\Queue\Queue;
use App\Core\Queue\QueueServiceLevel;
use App\EntryPoint\QueueJob\AccountingWriteJob;
use App\Integrations\AccountingSync\Models\ReconciliationError;
use App\Integrations\AccountingSync\ReadSync\ReadSyncJobClassFactory;
use App\Integrations\Exceptions\IntegrationException;

class RetryReconciliationError
{
    public function __construct(
        private Queue $queue,
    ) {
    }

    /**
     * Queues a reconciliation error to be retried.
     *
     * @throws IntegrationException
     */
    public function retry(ReconciliationError $error): void
    {
        if ($error->retried_at > 0) {
            throw new IntegrationException('Could not retry operation because it has already been retried');
        }

        $retryContext = (new RetryContextFactory())->make($error);
        if (!$retryContext) {
            throw new IntegrationException('Retries are not supported for this transaction');
        }

        // The existing reconciliation error should be removed at this point
        // because the handler does not necessarily clean up the error and
        // we do not want it left in a retry pending state. If there is a
        // follow on reconciliation error then the handler will create one.
        ReconciliationError::where('id', $retryContext->errorId)
            ->delete();

        if (!$retryContext->fromAccountingSystem) {
            // Retry a write error
            $this->queue->enqueue(AccountingWriteJob::class, array_merge($retryContext->data, [
                'tenant_id' => $error->tenant_id,
            ]));
        } else {
            // Retry a read error
            $jobClass = ReadSyncJobClassFactory::get($error->getIntegrationType());
            $this->queue->enqueue($jobClass, array_merge($retryContext->data, [
                'tenant_id' => $error->tenant_id,
                'single_sync' => true,
            ]), QueueServiceLevel::Batch);
        }
    }
}
