<?php

namespace App\SubscriptionBilling\PricingRules;

/**
 * Represents a `tiered` pricing rule.
 */
class TieredPricingRule extends AbstractRule
{
    use TieredRuleTrait;

    public function transform(array $lineItem, mixed $value): array
    {
        // determine the tier
        $lines = [];
        $quantity = round($lineItem['quantity']); // convert to a natural number
        foreach ($value as $tier) {
            if (!isset($tier->min_qty)) {
                $tier->min_qty = 0;
            } elseif (!isset($tier->max_qty)) {
                $tier->max_qty = PHP_INT_MAX;
            }

            // add a line item if the quantity includes this tier
            if ($quantity >= $tier->min_qty) {
                // find the quantity matches this tier
                if ($tier->min_qty > 0) {
                    $delta = $lineItem['quantity'] - $tier->min_qty + 1;
                    $max = $tier->max_qty - $tier->min_qty + 1;
                } else {
                    $delta = $lineItem['quantity'];
                    $max = $tier->max_qty;
                }
                $matchedQuantity = min($max, $delta);

                $newLine = $lineItem;
                $newLine['description'] = $this->tierDescription($tier);
                $newLine['unit_cost'] = $tier->unit_cost;
                $newLine['quantity'] = $matchedQuantity;

                // add the line
                $lines[] = $newLine;

                continue;
            }
        }

        return $lines;
    }
}
