<?php

namespace App\Tests\SubscriptionBilling\PricingRules;

use App\SubscriptionBilling\PricingRules\MarkupPricingRule;
use App\Tests\AppTestCase;

class MarkupPricingRuleTest extends AppTestCase
{
    use ScaledRuleTests;

    private function getRule(): MarkupPricingRule
    {
        return new MarkupPricingRule();
    }
}
