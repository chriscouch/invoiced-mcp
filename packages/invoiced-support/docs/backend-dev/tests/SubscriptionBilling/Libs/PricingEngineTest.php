<?php

namespace App\Tests\SubscriptionBilling\Libs;

use App\SubscriptionBilling\Models\Plan;
use App\Core\Utils\ValueObjects\Interval;
use App\SubscriptionBilling\Exception\PricingException;
use App\SubscriptionBilling\Libs\PricingEngine;
use App\Tests\AppTestCase;

class PricingEngineTest extends AppTestCase
{
    public function testPricePerUnit(): void
    {
        $engine = new PricingEngine();

        $plan = new Plan();
        $plan->internal_id = 1234;
        $plan->id = 'starter';
        $plan->name = 'Starter';
        $plan->amount = 100;
        $plan->currency = 'usd';
        $plan->interval_count = 2;
        $plan->interval = Interval::MONTH;
        $plan->pricing_mode = Plan::PRICING_PER_UNIT;

        $expected = [
            [
                'plan_id' => $plan->internal_id,
                'plan' => 'starter',
                'type' => 'plan',
                'name' => 'Starter',
                'description' => null,
                'quantity' => 101,
                'unit_cost' => 100,
                'metadata' => new \stdClass(),
            ],
        ];

        $this->assertEquals($expected, $engine->price($plan, 101));
    }

    public function testPriceVolume(): void
    {
        $engine = new PricingEngine();

        $plan = new Plan();
        $plan->internal_id = 1234;
        $plan->id = 'volume';
        $plan->name = 'Volume';
        $plan->amount = 0;
        $plan->currency = 'usd';
        $plan->interval_count = 2;
        $plan->interval = Interval::MONTH;
        $plan->pricing_mode = Plan::PRICING_VOLUME;
        $plan->tiers = [
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

        $expected = [
            [
                'plan_id' => 1234,
                'plan' => 'volume',
                'type' => 'plan',
                'name' => 'Volume',
                'description' => '101+ tier',
                'quantity' => 101,
                'unit_cost' => 70,
                'metadata' => new \stdClass(),
            ],
        ];

        $this->assertEquals($expected, $engine->price($plan, 101));
    }

    public function testPriceTiered(): void
    {
        $engine = new PricingEngine();

        $plan = new Plan();
        $plan->internal_id = 1234;
        $plan->id = 'tiered';
        $plan->name = 'Tiered';
        $plan->amount = 0;
        $plan->currency = 'usd';
        $plan->interval_count = 2;
        $plan->interval = Interval::MONTH;
        $plan->pricing_mode = Plan::PRICING_TIERED;
        $plan->tiers = [
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

        $expected = [
            [
                'plan_id' => 1234,
                'plan' => 'tiered',
                'type' => 'plan',
                'name' => 'Tiered',
                'description' => '0 - 50 tier',
                'quantity' => 50,
                'unit_cost' => 100,
                'metadata' => new \stdClass(),
            ],
            [
                'plan_id' => 1234,
                'plan' => 'tiered',
                'type' => 'plan',
                'name' => 'Tiered',
                'description' => '51 - 100 tier',
                'quantity' => 50,
                'unit_cost' => 80,
                'metadata' => new \stdClass(),
            ],
            [
                'plan_id' => 1234,
                'plan' => 'tiered',
                'type' => 'plan',
                'name' => 'Tiered',
                'description' => '101+ tier',
                'quantity' => 1,
                'unit_cost' => 70,
                'metadata' => new \stdClass(),
            ],
        ];

        $this->assertEquals($expected, $engine->price($plan, 101));
    }

    public function testCustomPlan(): void
    {
        $engine = new PricingEngine();

        $plan = new Plan();
        $plan->internal_id = 1234;
        $plan->id = 'custom';
        $plan->name = 'Custom';
        $plan->currency = 'usd';
        $plan->interval_count = 1;
        $plan->interval = Interval::MONTH;
        $plan->pricing_mode = Plan::PRICING_CUSTOM;

        $expected = [
            [
                'plan_id' => 1234,
                'plan' => 'custom',
                'type' => 'plan',
                'name' => 'Custom',
                'quantity' => 1,
                'unit_cost' => 100,
                'metadata' => new \stdClass(),
                'description' => null,
            ],
        ];

        $this->assertEquals($expected, $engine->price($plan, 1, 100));

        $expected = [
            [
                'plan_id' => 1234,
                'plan' => 'custom',
                'type' => 'plan',
                'name' => 'Custom',
                'quantity' => 2,
                'unit_cost' => 100,
                'metadata' => new \stdClass(),
                'description' => null,
            ],
        ];

        $this->assertEquals($expected, $engine->price($plan, 2, 100));
    }

    public function testCustomPlanMissingAmount(): void
    {
        $this->expectException(PricingException::class);
        $this->expectExceptionMessage('Plans priced with pricing mode \'custom\' require an amount value');

        $engine = new PricingEngine();

        $plan = new Plan();
        $plan->internal_id = 1234;
        $plan->id = 'custom';
        $plan->name = 'Custom';
        $plan->currency = 'usd';
        $plan->interval_count = 1;
        $plan->interval = Interval::MONTH;
        $plan->pricing_mode = Plan::PRICING_CUSTOM;

        $engine->price($plan, 1, null);
    }
}
