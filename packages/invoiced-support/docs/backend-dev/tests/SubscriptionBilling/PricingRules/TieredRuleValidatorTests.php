<?php

namespace App\Tests\SubscriptionBilling\PricingRules;

trait TieredRuleValidatorTests
{
    public function testValidate(): void
    {
        $tiers = [
            [
                'max_qty' => 5,
                'unit_cost' => 100,
            ],
            [
                'min_qty' => 6,
                'max_qty' => 25,
                'unit_cost' => 80,
            ],
            [
                'min_qty' => 26,
                'max_qty' => 100,
                'unit_cost' => 70,
            ],
            [
                'min_qty' => 101,
                'unit_cost' => 50,
            ],
        ];

        $rule = $this->getRule();
        $this->assertTrue($rule->validate(json_encode($tiers)));
    }

    public function testValidateNotInfinte(): void
    {
        $tiers = [
            [
                'min_qty' => 0,
                'max_qty' => 5,
                'unit_cost' => 100,
            ],
            [
                'min_qty' => 6,
                'unit_cost' => 70,
            ],
        ];

        $rule = $this->getRule();
        $this->assertTrue($rule->validate(json_encode($tiers)));
    }

    public function testValidateOverlap(): void
    {
        $tiers = [
            [
                'max_qty' => 5,
                'unit_cost' => 100,
            ],
            [
                'min_qty' => 5,
                'unit_cost' => 100,
            ],
        ];

        $rule = $this->getRule();
        $this->assertFalse($rule->validate(json_encode($tiers)));
        $this->assertEquals('Invalid pricing tiers because quantity ranges overlap.', $rule->getLastValidationError());

        $tiers = [
            [
                'min_qty' => 0,
                'max_qty' => 8,
                'unit_cost' => 100,
            ],
            [
                'min_qty' => 10,
                'unit_cost' => 100,
            ],
            [
                'min_qty' => 7,
                'max_qty' => 9,
                'unit_cost' => 100,
            ],
        ];

        $this->assertFalse($rule->validate(json_encode($tiers)));
        $this->assertEquals('Invalid pricing tiers because quantity ranges overlap.', $rule->getLastValidationError());
    }

    public function testValidateGap(): void
    {
        $tiers = [
            [
                'min_qty' => 0,
                'max_qty' => 5,
                'unit_cost' => 100,
            ],
            [
                'min_qty' => 10,
                'unit_cost' => 300,
            ],
            [
                'min_qty' => 7,
                'max_qty' => 9,
                'unit_cost' => 200,
            ],
        ];

        $rule = $this->getRule();
        $this->assertFalse($rule->validate(json_encode($tiers)));
        $this->assertEquals('Invalid pricing tiers because quantity ranges do not cover all possible quantities.', $rule->getLastValidationError());
    }

    public function testValidateNoCap(): void
    {
        $tiers = [
            [
                'min_qty' => 0,
                'max_qty' => 5,
                'unit_cost' => 100,
            ],
            [
                'min_qty' => 6,
                'max_qty' => 10,
                'unit_cost' => 200,
            ],
        ];

        $rule = $this->getRule();
        $this->assertFalse($rule->validate(json_encode($tiers)));
        $this->assertEquals('The last tier must be have no cap.', $rule->getLastValidationError());
    }

    public function testValidateInvalidUnitCost(): void
    {
        $tiers = [
            [
                'min_qty' => 0,
                'max_qty' => 10,
            ],
        ];

        $rule = $this->getRule();
        $this->assertFalse($rule->validate(json_encode($tiers)));
        $this->assertEquals('Tier 1 is missing a unit cost.', $rule->getLastValidationError());

        $tiers = [
            [
                'min_qty' => 0,
                'max_qty' => 10,
                'unit_cost' => [],
            ],
        ];

        $rule = $this->getRule();
        $this->assertFalse($rule->validate(json_encode($tiers)));
        $this->assertEquals('Tier 1 unit cost should be a number.', $rule->getLastValidationError());
    }

    public function testValidateInvalidMinQty(): void
    {
        $tiers = [
            [
                'min_qty' => [],
                'unit_cost' => 100,
            ],
        ];

        $rule = $this->getRule();
        $this->assertFalse($rule->validate(json_encode($tiers)));
        $this->assertEquals('Tier 1 minimum quantity should be a number or empty.', $rule->getLastValidationError());
    }

    public function testValidateInvalidMaxQty(): void
    {
        $tiers = [
            [
                'max_qty' => [],
                'unit_cost' => 100,
            ],
        ];

        $rule = $this->getRule();
        $this->assertFalse($rule->validate(json_encode($tiers)));
        $this->assertEquals('Tier 1 maximum quantity should be a number or empty.', $rule->getLastValidationError());
    }

    public function testValidateInvalidQtyRange(): void
    {
        $tiers = [
            [
                'min_qty' => 3,
                'max_qty' => 2,
                'unit_cost' => 100,
            ],
        ];

        $rule = $this->getRule();
        $this->assertFalse($rule->validate(json_encode($tiers)));
        $this->assertEquals('Tier 1 has an invalid quantity range: 3 - 2', $rule->getLastValidationError());
    }

    public function testValidateMalformedTier(): void
    {
        $tiers = [
            1234,
        ];

        $rule = $this->getRule();
        $this->assertFalse($rule->validate(json_encode($tiers)));
        $this->assertEquals('Tier is malformed - must be an object.', $rule->getLastValidationError());
    }

    public function testValidateExtraProperty(): void
    {
        $tiers = [
            [
                'max_qty' => 5,
                'unit_cost' => 100,
                'test' => true,
            ],
        ];

        $rule = $this->getRule();
        $this->assertFalse($rule->validate(json_encode($tiers)));
        $this->assertEquals('Tier 1 has an invalid property: test', $rule->getLastValidationError());
    }

    public function testValidateNotJson(): void
    {
        $rule = $this->getRule();
        $this->assertFalse($rule->validate('not json'));
        $this->assertEquals('Rule value must be a JSON encoded array.', $rule->getLastValidationError());
    }

    public function testSerialization(): void
    {
        $tiers = [
            (object) [
                'max_qty' => 5,
                'unit_cost' => 100,
                'test' => true,
            ],
        ];

        $rule = $this->getRule();
        $serialized = $rule->serialize($tiers);
        $this->assertTrue(is_string($serialized));

        // should not serialize already serialized value
        $this->assertEquals($serialized, $rule->serialize($serialized));

        $deserialized = $rule->deserialize($serialized);
        $this->assertEquals($tiers, $deserialized);

        // should not de-serialize already de-serialized value
        $this->assertEquals($tiers, $rule->deserialize($deserialized));
    }
}
