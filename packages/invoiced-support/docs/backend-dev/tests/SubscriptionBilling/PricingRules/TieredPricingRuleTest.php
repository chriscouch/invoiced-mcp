<?php

namespace App\Tests\SubscriptionBilling\PricingRules;

use App\SubscriptionBilling\PricingRules\TieredPricingRule;
use App\Tests\AppTestCase;

class TieredPricingRuleTest extends AppTestCase
{
    use TieredRuleValidatorTests;

    private function getRule(): TieredPricingRule
    {
        return new TieredPricingRule();
    }

    private function getTiers(): array
    {
        return [
            (object) [
                'max_qty' => 50,
                'unit_cost' => 100,
            ],
            (object) [
                'min_qty' => 51,
                'max_qty' => 100,
                'unit_cost' => 80,
            ],
            (object) [
                'min_qty' => 101,
                'unit_cost' => 70,
            ],
        ];
    }

    public function testTransform(): void
    {
        $rule = $this->getRule();
        $tiers = $this->getTiers();

        $lineItem = [
            'name' => 'Test',
            'quantity' => 200,
        ];
        $transformed = $rule->transform($lineItem, $tiers);

        $expected = [
            [
                'name' => 'Test',
                'quantity' => 50,
                'unit_cost' => 100,
                'description' => '0 - 50 tier',
            ],
            [
                'name' => 'Test',
                'quantity' => 50,
                'unit_cost' => 80,
                'description' => '51 - 100 tier',
            ],
            [
                'name' => 'Test',
                'quantity' => 100,
                'unit_cost' => 70,
                'description' => '101+ tier',
            ],
        ];
        $this->assertEquals($expected, $transformed);
    }

    public function testTransformDecimalQuantity(): void
    {
        $rule = $this->getRule();
        $tiers = $this->getTiers();
        $lineItem = [
            'name' => 'Test',
            'quantity' => 0.25,
        ];
        $transformed = $rule->transform($lineItem, $tiers);

        $expected = [
            [
                'name' => 'Test',
                'quantity' => 0.25,
                'unit_cost' => 100,
                'description' => '0 - 50 tier',
            ],
        ];
        $this->assertEquals($expected, $transformed);
    }

    public function testTransformNoMatch(): void
    {
        $rule = $this->getRule();
        $tiers = [
            (object) [
                'min_qty' => 51,
                'max_qty' => 100,
                'unit_cost' => 80,
            ],
            (object) [
                'min_qty' => 101,
                'unit_cost' => 70,
            ],
        ];

        $lineItem = [
            'name' => 'Test',
            'quantity' => 50,
        ];
        $transformed = $rule->transform($lineItem, $tiers);

        $this->assertEquals([], $transformed);
    }
}
