<?php

namespace App\Tests\SubscriptionBilling\PricingRules;

trait ScaledRuleTests
{
    public function testValidateNumber(): void
    {
        $rule = $this->getRule();
        $this->assertTrue($rule->validate('2'));
        $this->assertTrue($rule->validate('1.23'));
        $this->assertTrue($rule->validate('0'));
    }

    public function testValidatePercentage(): void
    {
        $rule = $this->getRule();
        $this->assertTrue($rule->validate('2%'));
        $this->assertTrue($rule->validate('1.23%'));
        $this->assertTrue($rule->validate('0%'));
    }

    public function testValidateInvalidNumber(): void
    {
        $rule = $this->getRule();
        $this->assertFalse($rule->validate('not a number'));
        $this->assertFalse($rule->validate('1f'));
        $this->assertEquals('Could not validate rule value. Must be a number or percentage (i.e. "2%").', $rule->getLastValidationError());
    }

    public function testValidateInvalidPercentage(): void
    {
        $rule = $this->getRule();
        $this->assertFalse($rule->validate('not a percent%'));
        $this->assertFalse($rule->validate('1f%'));
        $this->assertEquals('Could not validate rule value. Must be a number or percentage (i.e. "2%").', $rule->getLastValidationError());
    }

    public function testSerializationFixedNumber(): void
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

    public function testSerializationPercentage(): void
    {
        $value = '10%';

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
