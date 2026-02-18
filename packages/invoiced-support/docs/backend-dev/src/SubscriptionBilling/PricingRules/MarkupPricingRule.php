<?php

namespace App\SubscriptionBilling\PricingRules;

/**
 * Represents a `markup` pricing rule.
 */
class MarkupPricingRule extends AbstractRule
{
    use ScaledRuleTrait;

    public function transform(array $lineItem, mixed $value): array
    {
        // TODO: Implement transform() method.

        return [$lineItem];
    }
}
