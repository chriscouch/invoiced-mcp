<?php

namespace App\EntryPoint\CronJob;

use App\Companies\Models\Company;
use App\Core\Queue\Queue;
use App\Core\Queue\QueueServiceLevel;
use App\EntryPoint\QueueJob\AutoPayJob;
use App\PaymentProcessing\Models\PaymentMethod;
use Doctrine\DBAL\Connection;

class AutoPay extends AbstractTaskQueueCronJob
{
    public function __construct(private Connection $connection, private Queue $queue)
    {
    }

    public static function getLockTtl(): int
    {
        return 1800;
    }

    public function getTasks(): iterable
    {
        return $this->connection->fetchFirstColumn(
            "SELECT DISTINCT tenant_id FROM Invoices WHERE
                autopay = true
                AND next_payment_attempt <= ?
                AND status IN ('past_due', 'viewed', 'sent', 'not_sent')",
            [time()]
        );
    }

    /**
     * @param int $task
     */
    public function runTask(mixed $task): bool
    {
        $company = Company::find($task);
        if (null === $company || !$company->billingStatus()->isActive() || !PaymentMethod::acceptsAutoPay($company)) {
            return false;
        }

        $this->queue->enqueue(AutoPayJob::class, [
            'tenant_id' => $task,
        ], QueueServiceLevel::Batch);

        return true;
    }
}
