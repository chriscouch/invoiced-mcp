<?php

namespace App\SubscriptionBilling\Libs;

use App\Core\I18n\ValueObjects\Money;
use App\SubscriptionBilling\Models\PendingLineItem;
use App\SubscriptionBilling\Models\Subscription;
use App\SubscriptionBilling\Models\SubscriptionAddon;
use App\SubscriptionBilling\ValueObjects\SubscriptionStatus;
use Carbon\CarbonImmutable;

final class Proration
{
    const DATE_FORMAT = 'M j, Y';
    private CarbonImmutable $prorationDate;
    private string $currency;
    private ProrationDiffer $diff;
    private array $lines;

    public function __construct(private Subscription $before, private Subscription $after, ?CarbonImmutable $prorationDate = null)
    {
        $this->prorationDate = $prorationDate ?: new CarbonImmutable();
        $this->currency = $before->plan()->currency;
        $this->diff = new ProrationDiffer($this->before, $this->after);
        $this->lines = $this->calculateLines();
    }

    /**
     * Gets the total of the proration.
     */
    public function getTotal(): Money
    {
        $total = new Money($this->currency, 0);
        foreach ($this->lines as $line) {
            $unitCost = Money::fromDecimal($this->currency, $line['quantity'] * $line['unit_cost']);
            $total = $total->add($unitCost);
        }

        return $total;
    }

    /**
     * Checks if the proration involves a billing cycle change.
     */
    public function changedCycle(): bool
    {
        return $this->diff->changedCycle();
    }

    /**
     * Checks if the proration involves a plan change.
     */
    public function changedPlan(): bool
    {
        return $this->diff->changedPlan();
    }

    /**
     * Checks if the proration involves a quantity change.
     */
    public function changedQuantity(): bool
    {
        return $this->diff->changedQuantity();
    }

    /**
     * Checks if the proration involves an amount change.
     */
    public function changedAmount(): bool
    {
        return $this->diff->changedAmount();
    }

    /**
     * Checks if the proration involves an addon change.
     */
    public function changedAddons(): bool
    {
        return $this->diff->changedAddons();
    }

    /**
     * Returns the line items from prorations.
     */
    public function getLines(): array
    {
        return $this->lines;
    }

    /**
     * Applies the proration. This will generate pending line items if
     * a non-zero proration was calculated.
     *
     * @return bool whether a proration was applied
     */
    public function apply(): bool
    {
        // Trialing, canceled, and finished transactions cannot be prorated
        if (in_array($this->after->status, [SubscriptionStatus::TRIALING, SubscriptionStatus::FINISHED, SubscriptionStatus::CANCELED])) {
            return false;
        }

        // do nothing when the proration = 0
        $total = $this->getTotal();
        if ($total->isZero()) {
            return false;
        }

        // generate pending line items
        $success = true;
        $lines = $this->buildPendingLineItems();
        foreach ($lines as $line) {
            $success = $line->save() && $success;
        }

        return $success;
    }

    //
    // Proration Calculations
    //

    /**
     * Calculates the line items.
     */
    private function calculateLines(): array
    {
        if (!$this->diff->hasProrationChange()) {
            return [];
        }

        // If the billing cycle is changing, i.e. monthly -> annual
        // then a new billing cycle will be started immediately,
        // and the remaining amount on the existing subscription
        // should be credited.
        if ($this->diff->changedCycle()) {
            $lines = $this->calculateChangedBillingCycle();

            // The billing cycle is not changing so prorate the
            // remaining amount on the existing plan, and credit it.
            // Then prorate the remaining amount on the new plan
            // and charge for it.
        } else {
            $lines = $this->calculateSameBillingCycle();
        }

        // Add subscription parameters to each line item.
        foreach ($lines as &$line) {
            $line['subscription_id'] = $this->before->id();
            $line['period_start'] = $this->prorationDate->getTimestamp();
            $line['period_end'] = $this->before->period_end;
            $line['prorated'] = true;
        }

        return $lines;
    }

    /**
     * Calculates prorations when the billing cycle changes,
     * i.e. monthly -> yearly.
     */
    private function calculateChangedBillingCycle(): array
    {
        $lines = [];

        // determine the percent remaining in cycle
        $percent = $this->before->billingPeriods()
            ->percentTimeRemaining($this->prorationDate);

        // credit prorated remaining time on previous plan
        $line = $this->getSubscriptionLine($this->before);
        $lines[] = $this->applyAddedOrRemovedPlan($percent, $line, $this->before->quantity, true);

        // credit prorated remaining time with previous addons
        foreach ($this->before->getAddons() as $addon) {
            $line = $this->getAddonLine($addon);
            $lines[] = $this->applyAddedOrRemovedPlan($percent, $line, $addon->quantity, true);
        }

        return $lines;
    }

    /**
     * Calculates prorations when the billing cycle DOES NOT
     * change.
     */
    private function calculateSameBillingCycle(): array
    {
        $lines = [];

        // determine the percent remaining in cycle
        $percent = $this->before->billingPeriods()
            ->percentTimeRemaining($this->prorationDate);

        // when changing plans then credit the remaining time on the
        // old plan and charge for the remaining time on the new plan
        if ($this->diff->changedPlan() || ($this->diff->changedQuantity() && $this->diff->changedAmount())) {
            $line = $this->getSubscriptionLine($this->before);
            $lines[] = $this->applyAddedOrRemovedPlan($percent, $line, $this->before->quantity, true);

            $line = $this->getSubscriptionLine($this->after);
            $lines[] = $this->applyAddedOrRemovedPlan($percent, $line, $this->after->quantity, false);
        } elseif ($this->diff->changedQuantity()) {
            $line = $this->getSubscriptionLine($this->after);
            $qtyDelta = $this->after->quantity - $this->before->quantity;
            $lines[] = $this->applyChangedQuantity($percent, $line, $qtyDelta);
        } elseif ($this->diff->changedAmount()) {
            // Since changedPlan() is checked first it's implied
            // that a change in amount value will be from float -> float
            // since:
            //    1. null -> float OR float -> null is the only other option
            //    2. the scenarios in point 1 imply a changed plan.
            $line = $this->after->plan()->lineItem();
            $amountDelta = (float) $this->after->amount - $this->before->amount;
            $lines[] = $this->applyChangedAmount($percent, $line, $this->after->quantity, $amountDelta);
        }

        // Process removed addons
        foreach ($this->diff->getAddonsRemoved() as $addon) {
            $line = $this->getAddonLine($addon);
            $lines[] = $this->applyAddedOrRemovedPlan($percent, $line, $addon->quantity, true);
        }

        // Process added addons
        foreach ($this->diff->getAddonsAdded() as $addon) {
            $line = $this->getAddonLine($addon);
            $lines[] = $this->applyAddedOrRemovedPlan($percent, $line, $addon->quantity, false);
        }

        // Process modified addons
        foreach ($this->diff->getAddonsModified() as $addon) {
            $line = $this->getAddonLine($addon);
            if (isset($addon->amount_delta) && isset($addon->quantity_delta)) {
                $lines[] = $this->applyAddedOrRemovedPlan($percent, $line, $addon->quantity, true);
                $lines[] = $this->applyAddedOrRemovedPlan($percent, $line, $addon->quantity, false);
            } elseif (isset($addon->amount_delta)) {
                // This ONLY SUPPORTS custom pricing
                $lines[] = $this->applyChangedAmount($percent, $line, $addon->quantity, $addon->amount_delta);
            } elseif (isset($addon->quantity_delta)) {
                $lines[] = $this->applyChangedQuantity($percent, $line, $addon->quantity_delta);
            }
        }

        return $lines;
    }

    private function getSubscriptionLine(Subscription $subscription): array
    {
        // TODO: this does not support tiered and volume pricing
        $lines = $subscription->planLineItems();

        return $lines[0];
    }

    private function getAddonLine(SubscriptionAddon $addon): array
    {
        // TODO: this does not support tiered and volume pricing
        $lines = $addon->lineItems();

        return $lines[0];
    }

    /**
     * Calculates the line to add or remove a plan.
     */
    private function applyAddedOrRemovedPlan(float $percent, array $line, float $quantity, bool $removed): array
    {
        $qtyDelta = $removed ? -$quantity : $quantity;

        return $this->applyChangedQuantity($percent, $line, $qtyDelta);
    }

    /**
     * Calculates the line for a plan quantity proration.
     */
    private function applyChangedQuantity(float $percent, array $line, float $qtyDelta): array
    {
        // line quantity = quantity delta * percent remaining
        // with up to 4 decimals of precision
        // upgrades will produce a charge
        // downgrades will produce a credit
        $line['quantity'] = round($percent * $qtyDelta, 4);

        // The line item description will be in the format:
        // (added|removed 1)
        // [item description]
        $description = ' (';
        if ($qtyDelta < 0) {
            $description .= 'removed '.abs($qtyDelta);
        } else {
            $description .= 'added '.abs($qtyDelta);
        }
        $description .= ')';

        $line['description'] = trim($description."\n".$line['description']);

        return $line;
    }

    /**
     * Calculates the line for a unit cost proration of a custom priced plan.
     */
    private function applyChangedAmount(float $percent, array $line, float $quantity, float $amountDelta): array
    {
        // line amount = amount delta * percent remaining
        // with up to 4 decimals of precision
        // upgrades will produce a charge
        // downgrades will produce a credit
        $line['unit_cost'] = $amountDelta;
        $line['quantity'] = round($percent * $quantity, 4);

        // The line item description will be in the format:
        // (increased|decreased price)
        // [item description]
        $description = '(';
        $description .= ($amountDelta > 0) ? 'increased price' : 'decreased price';
        $description .= ')';
        $line['description'] = trim("$description\n{$line['description']}");

        return $line;
    }

    //
    // Proration Application
    //

    /**
     * Builds pending line items for this proration.
     *
     * @return PendingLineItem[]
     */
    public function buildPendingLineItems(): array
    {
        $lines = [];

        foreach ($this->lines as $line) {
            $pendingLine = new PendingLineItem();
            $pendingLine->setParent($this->before->customer());

            foreach ($line as $k => $v) {
                $pendingLine->$k = $v;
            }

            $lines[] = $pendingLine;
        }

        return $lines;
    }
}
