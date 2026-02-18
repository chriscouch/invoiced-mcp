<?php

namespace App\SubscriptionBilling\PricingRules;

/**
 * Represents a `markdown` pricing rule.
 */
class MarkdownPricingRule extends AbstractRule
{
    use ScaledRuleTrait;

    public function transform(array $lineItem, mixed $value): array
    {
        // TODO: Implement transform() method.

        return [$lineItem];
    }
}
