<?php

namespace App\SubscriptionBilling\PricingRules;

/**
 * Represents a `volume` pricing rule.
 */
class VolumePricingRule extends AbstractRule
{
    use TieredRuleTrait;

    public function transform(array $lineItem, mixed $value): array
    {
        // determine the tier
        $quantity = round($lineItem['quantity']); // convert to a natural number
        foreach ($value as $tier) {
            if (!isset($tier->min_qty)) {
                $tier->min_qty = 0;
            } elseif (!isset($tier->max_qty)) {
                $tier->max_qty = PHP_INT_MAX;
            }

            if ($quantity >= $tier->min_qty && $quantity <= $tier->max_qty) {
                $lineItem['description'] = $this->tierDescription($tier);
                $lineItem['unit_cost'] = $tier->unit_cost;

                break;
            }
        }

        return [$lineItem];
    }
}
