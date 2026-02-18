<?php

namespace App\EntryPoint\CronJob;

use App\Companies\Models\Company;
use App\Core\Multitenant\TenantContext;
use App\Core\Orm\Query;
use App\PaymentProcessing\Exceptions\TransactionStatusException;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Operations\UpdateChargeStatus;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Throwable;

/**
 * This is a recurring job that will run every hour to check the status
 * of 1,000 pending charges at a time. This sorts charges based
 * on the last status check to create a priority queue. When a charge
 * is checked it has its last check timestamp updated to remove it from the
 * queue and re-add it to the end.
 *
 * When the system does not have many pending charges in its queue (< 1,000)
 * then this will check for a status update every hour. If the system has > 1,000
 * pending charges in its queue then charges will be checked less frequently.
 * This is ok because in reality it is ok if clearing pending charges lags a bit.
 * The total capacity of this system is processing 1,000 pending charges per hour
 * or 24,000 pending charges per day.
 */
class UpdatePendingCharges extends AbstractTaskQueueCronJob implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const BATCH_SIZE = 1000;

    private int $count;

    public function __construct(private TenantContext $tenant, private UpdateChargeStatus $updateChargeStatus)
    {
    }

    public static function getLockTtl(): int
    {
        return 1800;
    }

    public function getTasks(): iterable
    {
        $query = $this->getPendingCharges();
        $this->count = $query->count();

        return $query->first(self::BATCH_SIZE);
    }

    public function getTaskCount(): int
    {
        return $this->count;
    }

    /**
     * @param Charge $task
     */
    public function runTask(mixed $task): bool
    {
        $company = $task->tenant();

        // check if the company is in good standing
        if (!$company->billingStatus()->isActive()) {
            return false;
        }

        // IMPORTANT: set the current tenant to enable multitenant operations
        $this->tenant->set($company);

        try {
            $saved = $this->updateChargeStatus->update($task);
        } catch (Throwable $e) {
            if (!($e instanceof TransactionStatusException)) {
                $this->logger->error('An exception when updating a pending charge status', ['exception' => $e]);
            }

            $saved = false;
        }

        // IMPORTANT: clear the current tenant after we are done
        $this->tenant->clear();

        return $saved;
    }

    /**
     * Gets the pending charges that should be checked. The results are sorted by
     * the charges that have gone the longest without a status
     * check to create a basic priority queue.
     */
    public function getPendingCharges(): Query
    {
        return Charge::queryWithoutMultitenancyUnsafe()
            ->join(Company::class, 'Charges.tenant_id', 'Companies.id')
            ->where('status', Charge::PENDING)
            ->where('payment_source_id IS NOT NULL')
            ->where('Companies.canceled=0')
            ->sort('last_status_check ASC,id ASC');
    }
}
