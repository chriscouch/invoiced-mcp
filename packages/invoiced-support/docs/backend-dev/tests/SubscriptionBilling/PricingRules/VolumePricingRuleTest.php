<?php

namespace App\Tests\SubscriptionBilling\PricingRules;

use App\SubscriptionBilling\PricingRules\VolumePricingRule;
use App\Tests\AppTestCase;

class VolumePricingRuleTest extends AppTestCase
{
    use TieredRuleValidatorTests;

    private function getRule(): VolumePricingRule
    {
        return new VolumePricingRule();
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
            'quantity' => 55,
        ];
        $transformed = $rule->transform($lineItem, $tiers);

        $expected = [
            [
                'quantity' => 55,
                'unit_cost' => 80,
                'description' => '51 - 100 tier',
            ],
        ];
        $this->assertEquals($expected, $transformed);
    }

    public function testTransformMinTier(): void
    {
        $rule = $this->getRule();
        $tiers = $this->getTiers();

        $lineItem = [
            'quantity' => 10,
        ];
        $transformed = $rule->transform($lineItem, $tiers);

        $expected = [
            [
                'quantity' => 10,
                'unit_cost' => 100,
                'description' => '0 - 50 tier',
            ],
        ];
        $this->assertEquals($expected, $transformed);
    }

    public function testTransformMaxTier(): void
    {
        $rule = $this->getRule();
        $tiers = $this->getTiers();

        $lineItem = [
            'quantity' => 200,
        ];
        $transformed = $rule->transform($lineItem, $tiers);

        $expected = [
            [
                'quantity' => 200,
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
            'quantity' => 0.25,
        ];
        $transformed = $rule->transform($lineItem, $tiers);

        $expected = [
            [
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
        $tiers = [];

        $lineItem = [
            'quantity' => 10,
        ];
        $transformed = $rule->transform($lineItem, $tiers);

        $expected = [
            [
                'quantity' => 10,
            ],
        ];
        $this->assertEquals($expected, $transformed);
    }
}
