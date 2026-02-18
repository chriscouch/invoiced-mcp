<?php

namespace App\SubscriptionBilling\Libs;

use App\SubscriptionBilling\Models\Subscription;
use App\SubscriptionBilling\Models\SubscriptionAddon;

/**
 * This class determines the differences
 * to a subscription before and after
 * a modification. It will determine any relevant changes
 * for the purposes of prorations. For example,
 * whether the plan was changed and what addons
 * were changed.
 */
final class ProrationDiffer
{
    private bool $changedCycle;
    private bool $changedPlan;
    private bool $changedQuantity;
    private bool $changedAmount;
    private bool $changedAddons;
    private array $addonsAdded = [];
    private array $addonsModified = [];
    private array $addonsRemoved = [];

    public function __construct(private Subscription $before, private Subscription $after)
    {
        $this->calculate();
    }

    /**
     * Checks if the operation involves a change
     * that could generate a proration.
     */
    public function hasProrationChange(): bool
    {
        return $this->changedCycle || $this->changedPlan || $this->changedQuantity || $this->changedAddons || $this->changedAmount;
    }

    /**
     * Checks if the proration involves a billing cycle change.
     */
    public function changedCycle(): bool
    {
        return $this->changedCycle;
    }

    /**
     * Checks if the proration involves a plan change.
     */
    public function changedPlan(): bool
    {
        return $this->changedPlan;
    }

    /**
     * Checks if the proration involves a quantity change.
     */
    public function changedQuantity(): bool
    {
        return $this->changedQuantity;
    }

    /**
     * Checks if the proration involves a change in subscription amount (custom plan price).
     */
    public function changedAmount(): bool
    {
        return $this->changedAmount;
    }

    /**
     * Checks if the proration involves an addon change.
     */
    public function changedAddons(): bool
    {
        return $this->changedAddons;
    }

    /**
     * @return SubscriptionAddon[]
     */
    public function getAddonsAdded(): array
    {
        return $this->addonsAdded;
    }

    /**
     * @return SubscriptionAddon[]
     */
    public function getAddonsRemoved(): array
    {
        return $this->addonsRemoved;
    }

    /**
     * @return SubscriptionAddon[]
     */
    public function getAddonsModified(): array
    {
        return $this->addonsModified;
    }

    /**
     * Calculates the line items.
     */
    private function calculate(): void
    {
        // determine the billing cycles
        $beforePlan = $this->before->plan();
        $afterPlan = $this->after->plan();
        $oldBillingCycle = $beforePlan->interval()->duration();
        $newBillingCycle = $afterPlan->interval()->duration();

        $this->changedCycle = $oldBillingCycle != $newBillingCycle;

        // determine line item changes
        $this->changedPlan = $beforePlan->id != $afterPlan->id;
        $this->changedQuantity = $this->before->quantity != $this->after->quantity;

        // determine amount change
        $beforeAmount = $this->before->amount;
        $afterAmount = $this->after->amount;
        $this->changedAmount = $beforeAmount != $afterAmount;

        // determine how addons were changed
        $addonsBefore = $this->getAddonValues($this->before->getAddons());
        $addonsAfter = $this->getAddonValues($this->after->getAddons());

        $this->calculateAddonsDiff($addonsBefore, $addonsAfter);
    }

    /**
     * Builds a map of addons and quantities by addon ID.
     * The addon ID is based on the plan or catalog item ID.
     */
    private function getAddonValues(array $addons): array
    {
        $quantities = [];

        foreach ($addons as $addon) {
            if ($addon->catalog_item_id) {
                $id = 'item:'.$addon->catalog_item_id;
            } else {
                $id = 'plan:'.$addon->plan_id;
            }

            // NOTE: A 0 is used in place of null
            // when $addon->amount is null. This is used
            // when calculating the difference in amount
            // on the addon.
            if (!isset($quantities[$id])) {
                $quantities[$id] = [
                    'quantity' => $addon->quantity,
                    'amount' => $addon->amount ?? 0,
                    'addon' => $addon,
                ];
            } else {
                $quantities[$id]['quantity'] += $addon->quantity;
                $quantities[$id]['amount'] += ($addon->amount ?? 0);
            }
        }

        return $quantities;
    }

    /**
     * Calculates the diff between addons before
     * and after a change.
     */
    private function calculateAddonsDiff(array $addonsBefore, array $addonsAfter): void
    {
        // find addons that were removed or modified
        foreach ($addonsBefore as $id => $before) {
            // check for a modification
            if (isset($addonsAfter[$id])) {
                // track amount and quantity changes
                $after = $addonsAfter[$id];
                if ($before['quantity'] != $after['quantity'] && $before['amount'] != $after['amount']) {
                    $this->addonsRemoved[] = $before['addon'];
                    $this->addonsAdded[] = $after['addon'];
                } elseif ($before['quantity'] != $after['quantity']) {
                    $after['addon']->quantity_delta = $after['quantity'] - $before['quantity'];
                    $this->addonsModified[] = $after['addon'];
                } elseif ($before['amount'] != $after['amount']) {
                    $after['addon']->amount_delta = $after['amount'] - $before['amount'];
                    $this->addonsModified[] = $after['addon'];
                }
            } else {
                $this->addonsRemoved[] = $before['addon'];
            }
        }

        // find addons that were added
        foreach ($addonsAfter as $id => $row) {
            if (!isset($addonsBefore[$id])) {
                $this->addonsAdded[] = $row['addon'];
            }
        }

        $this->changedAddons = count($this->addonsRemoved) > 0 || count($this->addonsAdded) > 0 || count($this->addonsModified) > 0;
    }
}
