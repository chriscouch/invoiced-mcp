<?php

namespace App\Chasing\LateFees;

use App\AccountsReceivable\Enums\InvoiceStatus;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\Chasing\Models\LateFee;
use App\Chasing\Models\LateFeeSchedule;
use App\Core\Orm\Exception\ModelException;
use App\Core\Queue\Queue;
use App\Core\Queue\QueueServiceLevel;
use App\Core\Utils\ModelLock;
use App\EntryPoint\QueueJob\RunLateFeeScheduleJob;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Connection;
use Symfony\Component\Lock\LockFactory;

/**
 * Adds late fees to past due invoices.
 */
class LateFeeAssessor
{
    const LOCK_TTL = 1800;

    public function __construct(
        private readonly Queue $queue,
        private readonly LockFactory $lockFactory,
        private readonly Connection $database,
        private readonly LateFeeApplierFactory $lateFeeApplierFactory
    ) {
    }

    public function queue(LateFeeSchedule $schedule): void
    {
        $this->queue->enqueue(RunLateFeeScheduleJob::class, [
            'schedule' => $schedule->id(),
            'tenant_id' => $schedule->tenant_id,
        ], QueueServiceLevel::Batch);
    }

    /**
     * Applies late fees to all invoices for a company
     * past the grace period.
     *
     * @return int # of invoices that have a late fee
     */
    public function assess(LateFeeSchedule $schedule): int
    {
        // grab the mutex lock
        $lock = new ModelLock($schedule, $this->lockFactory);
        if (!$lock->acquire(self::LOCK_TTL)) {
            return 0;
        }

        $schedule->tenant()->useTimezone();
        $invoices = $this->getLateInvoices($schedule);

        $n = 0;
        foreach ($invoices as $invoice) {
            try {
                if ($this->addLateFeeToInvoice($invoice, $schedule)) {
                    ++$n;
                }
            } catch (ModelException) {
                // ignore errors and move on
            }
        }

        // bypass ORM so updated_at does not change
        $this->database->executeStatement('UPDATE LateFeeSchedules SET last_run=? WHERE id=?', [
            CarbonImmutable::now()->toDateTimeString(),
            $schedule->id(),
        ]);

        // release the mutex lock
        $lock->release();

        return $n;
    }

    /**
     * Gets all late invoices according to the grace period.
     *
     * @return Invoice[]
     */
    public function getLateInvoices(LateFeeSchedule $schedule): iterable
    {
        $startDate = (new CarbonImmutable($schedule->start_date))->setTime(0, 0);

        $gracePeriod = $schedule->grace_period;
        if (0 == $gracePeriod) {
            $cutoff = time();
        } else {
            // get invoices due 1 day after the grace period
            $cutoff = time() - ($gracePeriod + 1) * 86400;
        }

        return Invoice::join(Customer::class, 'customer', 'Customers.id')
            ->where('Customers.late_fee_schedule_id', $schedule)
            ->where('draft', false)
            ->where('paid', false)
            ->where('closed', false)
            ->where('voided', false)
            ->where('status', InvoiceStatus::PastDue->value)
            ->where('late_fees', true)
            ->where('date', $startDate->getTimestamp(), '>=')
            ->where('due_date', $cutoff, '<=')
            ->where('payment_plan_id', null)
            ->all();
    }

    /**
     * Adds a late fee to an invoice.
     *
     * @throws ModelException
     */
    public function addLateFeeToInvoice(Invoice $invoice, LateFeeSchedule $schedule): bool
    {
        // first look for an last existing late fee application
        /** @var LateFee|null $lateFee */
        $lateFee = LateFee::where('invoice_id', $invoice->id())
            ->sort('id DESC')
            ->oneOrNull();

        $applicator = $this->lateFeeApplierFactory->create($lateFee, $schedule, $invoice);

        return $applicator->apply();
    }
}
