<?php

namespace App\Chasing\LateFees;

use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\LineItem;
use App\Chasing\Models\LateFee;
use App\Chasing\Models\LateFeeSchedule;
use App\Core\Database\TransactionManager;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Utils\ValueObjects\Interval;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Adds late fees to past due invoices.
 */
abstract class AbstractLateFeeApplier
{
    private float $fee;
    private bool $isPercent;
    private int $gracePeriod;
    private ?Interval $recurs;

    public function __construct(
        protected readonly TransactionManager $transactionManager,
        protected readonly ?LateFee $lateFee,
        LateFeeSchedule $schedule,
        protected readonly Invoice $invoice
    ) {
        $recurringInterval = null;
        if ($schedule->getRecurringDays() > 0) {
            $recurringInterval = new Interval($schedule->getRecurringDays(), Interval::DAY);
        }
        $this->recurs = $recurringInterval;

        $this->fee = $schedule->getAmount();
        $this->isPercent = $schedule->isPercent();
        $gracePeriod = $schedule->getGracePeriod();

        if ($gracePeriod < 0) {
            throw new InvalidArgumentException('Invalid grace period: '.$gracePeriod);
        }
        $this->gracePeriod = $gracePeriod;
    }

    /**
     * Gets the grace period.
     */
    public function getGracePeriod(): int
    {
        return $this->gracePeriod;
    }

    /**
     * Gets the recurring interval.
     */
    public function getRecurs(): ?Interval
    {
        return $this->recurs;
    }

    public function getDueDateUnGrace(): ?CarbonImmutable
    {
        if (!$this->invoice->due_date) {
            return null;
        }

        return CarbonImmutable::createFromTimestamp($this->invoice->due_date)->addDays($this->getGracePeriod());
    }

    abstract public function calculate(): Money;

    abstract public function apply(): bool;

    /**
     * Calculates an individual fee given an amount.
     */
    protected function calculateFee(Money $amount): Money
    {
        // flat fee
        if (!$this->isPercent) {
            return Money::fromDecimal($amount->currency, $this->fee);
        }

        // % fee
        $fee = $amount->toDecimal() * ($this->fee / 100.0);

        return Money::fromDecimal($amount->currency, $fee);
    }

    /**
     * @param int $order - try to make this last
     */
    protected function buildLateFeeLineItem(Money $fee, ?CarbonImmutable $date = null, int $order = 10000): LineItem
    {
        $lineItem = new LineItem();
        $lineItem->type = 'late_fee';
        $lineItem->name = 'Late fee';
        if ($date) {
            $lineItem->description = $date->format($this->invoice->tenant()->date_format);
        }
        $lineItem->quantity = 1;
        $lineItem->discountable = false;
        $lineItem->taxable = false;
        $lineItem->order = $order;
        $lineItem->unit_cost = $fee->toDecimal();
        $lineItem->setParent($this->invoice);

        return $lineItem;
    }
}
