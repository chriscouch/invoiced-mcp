<?php

namespace App\SubscriptionBilling\PricingRules;

/**
 * Represents a `price` pricing rule.
 */
class PriceOverridePricingRule extends AbstractRule
{
    public function transform(array $lineItem, mixed $value): array
    {
        $lineItem['unit_cost'] = $value;

        return [$lineItem];
    }

    public function validate(mixed $value): bool
    {
        if (!is_numeric($value)) {
            $this->lastError = 'Rule value must be numeric.';

            return false;
        }

        return true;
    }

    public function serialize(mixed $value): string
    {
        return (string) $value;
    }

    public function deserialize(mixed $value): mixed
    {
        return (float) $value;
    }
}
