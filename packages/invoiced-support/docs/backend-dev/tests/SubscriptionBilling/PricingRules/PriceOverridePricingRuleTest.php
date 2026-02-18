<?php

namespace App\Tests\SubscriptionBilling\PricingRules;

use App\SubscriptionBilling\PricingRules\PriceOverridePricingRule;
use App\Tests\AppTestCase;

class PriceOverridePricingRuleTest extends AppTestCase
{
    private function getRule(): PriceOverridePricingRule
    {
        return new PriceOverridePricingRule();
    }

    public function testTransform(): void
    {
        $rule = $this->getRule();

        $lineItem = [
            'quantity' => 10,
            'unit_cost' => 100,
        ];
        $transformed = $rule->transform($lineItem, '10');

        $expected = [
            [
                'quantity' => 10,
                'unit_cost' => 10.0,
            ],
        ];
        $this->assertEquals($expected, $transformed);
    }

    public function testValidate(): void
    {
        $rule = $this->getRule();

        $this->assertTrue($rule->validate('10'));
        $this->assertTrue($rule->validate('10.2'));
    }

    public function testValidateInvalid(): void
    {
        $rule = $this->getRule();

        $this->assertFalse($rule->validate('not a number'));
        $this->assertEquals('Rule value must be numeric.', $rule->getLastValidationError());
    }

    public function testSerialization(): void
    {
        $value = 10;

        $rule = $this->getRule();
        $serialized = $rule->serialize($value);
        $this->assertTrue(is_string($serialized));

        // should not serialize already serialized value
        $this->assertEquals($serialized, $rule->serialize($serialized));

        $deserialized = $rule->deserialize($serialized);
        $this->assertEquals($value, $deserialized);

        // should not de-serialize already de-serialized value
        $this->assertEquals($value, $rule->deserialize($deserialized));
    }
}
