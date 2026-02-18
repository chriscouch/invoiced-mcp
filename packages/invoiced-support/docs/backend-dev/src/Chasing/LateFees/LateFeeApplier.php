<?php

namespace App\Chasing\LateFees;

use App\AccountsReceivable\Models\LineItem;
use App\Chasing\Models\LateFee;
use App\Core\I18n\ValueObjects\Money;
use Carbon\CarbonImmutable;

/**
 * Legacy implementation lf late fee application.
 */
class LateFeeApplier extends AbstractLateFeeApplier
{
    public function apply(): bool
    {
        $recurs = $this->getRecurs();
        // already applied
        if (!$recurs && $this->lateFee) {
            return false;
        }

        $start = $this->getStartCalculations();
        if (!$start) {
            return false;
        }

        $now = CarbonImmutable::now();

        if ($start->isAfter($now)) {
            return false;
        }

        // get latest line item for appropriate sorting
        /** @var ?LineItem $latestLineItem */
        $latestLineItem = LineItem::where('invoice_id', $this->invoice->id())
            ->sort('order desc')
            ->oneOrNull();

        $order = 1000;
        if ($latestLineItem) {
            $order = $latestLineItem->order + 1;
        }

        while ($start->isBefore($now)) {
            // calculate the late fee
            $fee = $this->calculate();
            if (!$fee->isZero()) {
                $lineItem = $this->buildLateFeeLineItem($fee, $start, $order);
                ++$order;

                $this->transactionManager->perform(function () use ($lineItem, $start) {
                    $lineItem->saveOrFail();

                    $lateFee = new LateFee();
                    $lateFee->customer_id = $this->invoice->customer;
                    $lateFee->invoice_id = (int) $this->invoice->id();
                    $lateFee->line_item_id = (int) $lineItem->id();
                    $lateFee->date = $start;
                    $lateFee->version = 2;
                    $lateFee->saveOrFail();
                });
            }

            if (!$recurs) {
                return true;
            }
            $start = $start->addDays($recurs->numDays());
        }

        return true;
    }

    private function getStartCalculations(): ?CarbonImmutable
    {
        if ($this->lateFee) {
            $recurs = $this->getRecurs();
            if (!$this->lateFee->date || !$recurs) {
                return null;
            }

            return CarbonImmutable::instance($this->lateFee->date)->add($recurs->numDays().' days');
        }

        return $this->getDueDateUnGrace();
    }

    public function calculate(): Money
    {
        $subtotal = Money::fromDecimal($this->invoice->currency, $this->invoice->balance);

        return $this->calculateFee($subtotal);
    }
}
