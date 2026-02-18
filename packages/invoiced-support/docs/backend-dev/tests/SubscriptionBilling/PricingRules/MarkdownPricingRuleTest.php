<?php

namespace App\Tests\SubscriptionBilling\PricingRules;

use App\SubscriptionBilling\PricingRules\MarkdownPricingRule;
use App\Tests\AppTestCase;

class MarkdownPricingRuleTest extends AppTestCase
{
    use ScaledRuleTests;

    private function getRule(): MarkdownPricingRule
    {
        return new MarkdownPricingRule();
    }
}
