<?php

namespace App\AccountsReceivable\Libs;

use App\AccountsReceivable\Models\ReceivableDocument;
use App\Companies\Models\Company;
use App\Core\Database\TransactionManager;
use App\Core\Multitenant\TenantContext;
use App\EntryPoint\CronJob\AbstractTaskQueueCronJob;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Throwable;

abstract class AbstractReceivableDocumentStatusJob extends AbstractTaskQueueCronJob implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private TenantContext $tenant,
        private TransactionManager $transactionManager,
        protected Connection $database,
    ) {
    }

    /**
     * Gets all companies that have at least one past due document that has not been marked
     * past due yet.
     *
     * @return int[]
     */
    abstract public function getCompanies(): array;

    /**
     * Gets all past due documents that have not been marked
     * past due yet.
     *
     * @return ReceivableDocument[]
     */
    abstract public function getDocuments(Company $company): array;

    public static function getLockTtl(): int
    {
        return 59;
    }

    public function getTasks(): iterable
    {
        $results = [];
        foreach ($this->getCompanies() as $id) {
            $results[] = Company::findOrFail($id);
        }

        return $results;
    }

    /**
     * @param Company $task
     */
    public function runTask(mixed $task): bool
    {
        // IMPORTANT: set the current tenant to enable multitenant operations
        $this->tenant->set($task->tenant());

        try {
            $finished = true;
            foreach ($this->getDocuments($task) as $document) {
                $this->transactionManager->perform(function () use ($document) {
                    $document->updateStatus();
                });
            }
        } catch (Throwable $e) {
            // Do not let an exception block other companies from executing
            $this->logger->error('Could not mark receivable documents past due', ['exception' => $e]);

            $finished = false;
        }

        // IMPORTANT: clear the current tenant after we are done
        $this->tenant->clear();

        return $finished;
    }
}
