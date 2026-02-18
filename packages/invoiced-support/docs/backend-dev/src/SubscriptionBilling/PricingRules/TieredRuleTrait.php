<?php

namespace App\SubscriptionBilling\PricingRules;

/**
 * Implements the validation for tiered and volume pricing rules.
 */
trait TieredRuleTrait
{
    public function validate(mixed $value): bool
    {
        // value should be a JSON-encoded array
        $tiers = json_decode($value);
        if (!is_array($tiers)) {
            $this->lastError = 'Rule value must be a JSON encoded array.';

            return false;
        }

        // tiers should cover every natural number with no overlap
        $ranges = [];
        foreach ($tiers as $i => $tier) {
            if (!is_object($tier)) {
                $this->lastError = 'Tier is malformed - must be an object.';

                return false;
            }

            $range = $this->validateTier($tier, $i + 1);
            if (!$range) {
                return false;
            }
            $ranges[] = $range;
        }

        // sort the ranges
        usort($ranges, [$this, 'sortRanges']);

        if (0 !== $ranges[0][0]) {
            $this->lastError = 'The first tier must be 0 or left blank';

            return false;
        }

        // check if there is any overlap in the ranges
        $max = -1;
        foreach ($ranges as $range) {
            if ($range[0] <= $max) {
                $this->lastError = 'Invalid pricing tiers because quantity ranges overlap.';

                return false;
            }

            $max = $range[1];
        }

        // check if there is any gap in the ranges
        $max = -1;
        foreach ($ranges as $range) {
            if ($range[0] !== $max + 1) {
                $this->lastError = 'Invalid pricing tiers because quantity ranges do not cover all possible quantities.';

                return false;
            }

            $max = $range[1];
        }

        if (PHP_INT_MAX !== $max) {
            $this->lastError = 'The last tier must be have no cap.';

            return false;
        }

        return true;
    }

    /**
     * Validates a pricing tier.
     *
     * @param object $tier
     */
    public function validateTier($tier, int $index): ?array
    {
        // check for extra properties
        foreach ((array) $tier as $k => $v) {
            if (!in_array($k, ['unit_cost', 'max_qty', 'min_qty'])) {
                $this->lastError = "Tier $index has an invalid property: $k";

                return null;
            }
        }

        // validate the unit cost
        if (!isset($tier->unit_cost)) {
            $this->lastError = "Tier $index is missing a unit cost.";

            return null;
        } elseif (!is_numeric($tier->unit_cost)) {
            $this->lastError = "Tier $index unit cost should be a number.";

            return null;
        }

        // validate the min qty
        if (isset($tier->min_qty)) {
            if (!is_numeric($tier->min_qty)) {
                $this->lastError = "Tier $index minimum quantity should be a number or empty.";

                return null;
            }
        } else {
            $tier->min_qty = 0;
        }

        // validate the max qty
        if (isset($tier->max_qty)) {
            if (!is_numeric($tier->max_qty)) {
                $this->lastError = "Tier $index maximum quantity should be a number or empty.";

                return null;
            }
        } else {
            $tier->max_qty = PHP_INT_MAX;
        }

        // validate the range
        if ($tier->min_qty < 0 || $tier->max_qty < 0 || $tier->max_qty < $tier->min_qty) {
            $this->lastError = "Tier $index has an invalid quantity range: {$tier->min_qty} - {$tier->max_qty}";

            return null;
        }

        return [$tier->min_qty, $tier->max_qty];
    }

    /**
     * Sorts two ranges.
     */
    public function sortRanges(array $a, array $b): int
    {
        return $a[0] <=> $b[0];
    }

    public function serialize(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        return (string) json_encode($value);
    }

    public function deserialize(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        return (array) json_decode($value);
    }

    public function tierDescription(\stdClass $tier): string
    {
        if (PHP_INT_MAX == $tier->max_qty) {
            return $tier->min_qty.'+ tier';
        }

        return $tier->min_qty.' - '.$tier->max_qty.' tier';
    }
}
