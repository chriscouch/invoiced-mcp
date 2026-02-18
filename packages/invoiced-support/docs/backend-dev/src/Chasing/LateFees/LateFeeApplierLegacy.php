<?php

namespace App\Chasing\LateFees;

use App\AccountsReceivable\Models\LineItem;
use App\Chasing\Models\LateFee;
use App\Core\I18n\ValueObjects\Money;

/**
 * Legacy implementation lf late fee application.
 */
class LateFeeApplierLegacy extends AbstractLateFeeApplier
{
    public function apply(): bool
    {
        $lateFee = $this->lateFee;

        // If the line item reference is empty then that
        // means the late fee was removed.
        if ($lateFee && !$lateFee->line_item_id) {
            return false;
        }

        // calculate the late fee
        $fee = $this->calculate();
        if ($fee->isZero()) {
            return false;
        }

        // check if there is an existing line
        if ($lateFee) {
            $lineItem = $lateFee->relation('line_item_id');
        } else {
            $lineItem = LineItem::where('invoice_id', $this->invoice->id())
                ->where('type', 'late_fee')
                ->oneOrNull();
        }

        /** @var LineItem|null $lineItem */
        if (!$lineItem) {
            $lineItem = $this->buildLateFeeLineItem($fee);
        } else {
            $lineItem->unit_cost = $fee->toDecimal();
        }

        $this->transactionManager->perform(function () use ($lineItem, $lateFee) {
            $lineItem->saveOrFail();

            if (!$lateFee) {
                $lateFee = new LateFee();
                $lateFee->customer_id = $this->invoice->customer;
                $lateFee->invoice_id = (int) $this->invoice->id();
                $lateFee->line_item_id = (int) $lineItem->id();
                $lateFee->version = 1;
                $lateFee->saveOrFail();
            }
        });

        return true;
    }

    public function calculate(): Money
    {
        $invoice = $this->invoice;
        $fee = new Money($invoice->currency, 0);

        // determine how many days late the invoice is
        $n = floor((time() - $invoice->due_date) / 86400);

        if (!$invoice->due_date || $n <= $this->getGracePeriod()) {
            return $fee;
        }

        // calculate the late fee (minus previous late fees)
        // WARNING: This does not take into account any partial payments. If an
        // invoice with late fees get a partial payment then the late fee amount
        // will begin to disappear. For example:
        // 1. Invoice is $1,000. 1% late fee is $10.
        // 2. Customer pays $100.
        // 3. 1% late fee becomes $9.
        $subtotal = Money::fromDecimal($invoice->currency, $invoice->balance);

        foreach ($invoice->items() as $item) {
            if ('late_fee' == $item['type']) {
                $amount = Money::fromDecimal($invoice->currency, $item['amount']);
                $subtotal = $subtotal->subtract($amount);
            }
        }

        // calculate how many iterations of the late fee are needed
        // we know we need at least one since we are past the grace period
        $iterations = 1;

        // determine how many recurring iterations are needed
        if ($this->getRecurs() && $n - $this->getGracePeriod() > 0) {
            $recurring = $this->getRecurs()->numDays();
            $iterations += floor(($n - $this->getGracePeriod()) / $recurring);
        }

        for ($i = 0; $i < $iterations; ++$i) {
            $fee = $fee->add($this->calculateFee($subtotal->add($fee)));
        }

        return $fee;
    }
}
