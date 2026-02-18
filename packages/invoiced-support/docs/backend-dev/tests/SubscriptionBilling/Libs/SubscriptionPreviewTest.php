<?php

namespace App\Tests\SubscriptionBilling\Libs;

use App\AccountsReceivable\Models\Coupon;
use App\Core\Orm\Exception\ModelException;
use App\Core\Utils\ValueObjects\Interval;
use App\SalesTax\Models\TaxRate;
use App\SubscriptionBilling\Libs\SubscriptionPreview;
use App\SubscriptionBilling\Models\Plan;
use App\Tests\AppTestCase;

class SubscriptionPreviewTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::hasItem();
    }

    /**
     * @throws ModelException
     */
    public function testGenerate(): void
    {
        $plan = $this->makePlan('plan');
        $addon = $this->makePlan('addon');
        $preview = new SubscriptionPreview(self::$company);
        $preview->setPlan($plan->id)
            ->setQuantity(2)
            ->setAddons([['plan' => $addon->id]])
            ->setCustomer(self::$customer->id)
            ->setPendingLineItems([['catalog_item' => self::$item->id]]);

        $preview->generate();

        $invoice = $preview->getFirstInvoice();
        $this->assertCount(3, $invoice->items);
        $this->assertEquals($plan->id, $invoice->items[0]['plan']);
        $this->assertEquals(2, $invoice->items[0]['quantity']);
        $this->assertEquals($addon->id, $invoice->items[1]['plan']);
        $this->assertEquals(1, $invoice->items[1]['quantity']);
        $this->assertEquals(self::$item->id, $invoice->items[2]['catalog_item']);
        $this->assertEquals(1, $invoice->items[2]['quantity']);
        $this->assertEquals(1300.0, $invoice->total);

        $this->assertEquals(150.0, $preview->getMrr());
        $this->assertEquals(300.0, $preview->getRecurringTotal());

        $coupon = $this->makeCoupon();
        $discounts = [$coupon->id];
        $preview->setDiscounts($discounts);
        $preview->generate();
        $this->assertEquals(285.0, $preview->getRecurringTotal());

        $tax1 = $this->makeTax();
        $tax2 = $this->makeTax();
        $taxes = [$tax1->id];
        $preview->setTaxes($taxes);
        $preview->generate();
        $this->assertEquals(299.25, $preview->getRecurringTotal());

        $taxes[] = $tax2->id;
        $preview->setTaxes($taxes);
        $preview->generate();
        $this->assertEquals(313.5, $preview->getRecurringTotal());

        $coupon = $this->makeCoupon();
        $discounts[] = $coupon->id;
        $preview->setDiscounts($discounts);
        $preview->generate();
        $this->assertEquals(297, $preview->getRecurringTotal());

        $customerTax = $this->makeTax();
        self::hasCustomer();
        self::$customer->taxes = [$customerTax->id];
        self::$customer->saveOrFail();
        $preview->setCustomer(self::$customer->id);
        $preview->generate();
        $this->assertEquals(310.5, $preview->getRecurringTotal());

        // INVD-1523
        $preview->setAmount(50.3);
        $preview->setTiers(null);
        $preview->generate();
        $this->assertEquals(50.3, $preview->getFirstInvoice()->items[0]['unit_cost']);
        // INVD-1157
        $preview->setAmount(50);
        $preview->generate();
        $this->assertEquals(99, $preview->getMrr());
        $this->assertEquals(207, $preview->getRecurringTotal());
        $preview->setAddons([[
            'plan' => $addon->id,
            'amount' => 50,
        ]]);
        $preview->generate();
        $this->assertEquals(74.25, $preview->getMrr());
        $this->assertEquals(155.25, $preview->getRecurringTotal());
        $preview->setAddons([[
            'plan' => $addon->id,
            'amount' => 50,
            'quantity' => 2,
        ]]);
        $preview->generate();
        $this->assertEquals(99, $preview->getMrr());
        $this->assertEquals(207, $preview->getRecurringTotal());

        // tiered plan
        $tiers = [
            (object) [
                'max_qty' => 2,
                'unit_cost' => 100,
            ],
            (object) [
                'min_qty' => 3,
                'max_qty' => 6,
                'unit_cost' => 80,
            ],
            (object) [
                'min_qty' => 7,
                'unit_cost' => 70,
            ],
        ];
        $plan->pricing_mode = Plan::PRICING_TIERED;
        $plan->tiers = $tiers;
        $plan->saveOrFail();

        $addon->pricing_mode = Plan::PRICING_TIERED;
        $addon->tiers = $tiers;
        $addon->saveOrFail();

        // refresh
        $preview->setAmount(null);
        $preview->setAddons([
            [
                'plan' => $addon->id,
            ],
        ]);
        $preview->setQuantity(10);
        $preview->generate();
        $this->assertEquals(445.5, $preview->getMrr());
        $this->assertEquals(931.5, $preview->getRecurringTotal());

        $tiers = [
            (object) [
                'max_qty' => 2,
                'unit_cost' => 50,
            ],
            (object) [
                'min_qty' => 3,
                'max_qty' => 6,
                'unit_cost' => 40,
            ],
            (object) [
                'min_qty' => 7,
                'unit_cost' => 35,
            ],
        ];
        $preview->setTiers($tiers);
        $preview->setAmount(null);
        $preview->generate();
        $this->assertEquals(247.5, $preview->getMrr());
        $this->assertEquals(517.5, $preview->getRecurringTotal());

        $preview->setAddons([[
            'plan' => $addon->id,
            'amount' => 0,
            'quantity' => 1,
            'tiers' => $tiers,
        ]]);
        $preview->generate();
        $this->assertEquals(222.75, $preview->getMrr());
        $this->assertEquals(465.75, $preview->getRecurringTotal());

        // only two tiers affected

        $tiers = [
            (object) [
                'max_qty' => 2,
                'unit_cost' => 50,
            ],
            (object) [
                'min_qty' => 3,
                'max_qty' => 6,
                'unit_cost' => 40,
            ],
            (object) [
                'min_qty' => 17,
                'unit_cost' => 35,
            ],
        ];
        $preview->setTiers($tiers);
        $preview->generate();
        $this->assertEquals(153.45, $preview->getMrr());
        $this->assertEquals(320.85, $preview->getRecurringTotal());

        $preview->setAddons([[
            'plan' => $addon->id,
            'amount' => 0,
            'quantity' => 10,
            'tiers' => $tiers,
        ]]);
        $preview->generate();
        $this->assertEquals(257.4, $preview->getMrr());
        $this->assertEquals(538.2, $preview->getRecurringTotal());

        // INVD-1157 end

        // delete the plans and the preview should be the same as the last result
        $plan->archive();
        $addon->archive();
        $preview->generate();
        $this->assertEquals(257.4, $preview->getMrr());
        $this->assertEquals(538.2, $preview->getRecurringTotal());
    }

    private function makeTax(): TaxRate
    {
        $tax = new TaxRate();
        $tax->id = 'tax'.rand(0, 1000);
        $tax->name = 'Tax';
        $tax->value = 5;
        $tax->saveOrFail();

        return $tax;
    }

    public function makeCoupon(): Coupon
    {
        $coupon = new Coupon();
        $coupon->id = 'coupon'.str_replace('.', '', (string) microtime(true));
        $coupon->name = 'Coupon';
        $coupon->value = 5;
        $coupon->saveOrFail();

        return $coupon;
    }

    /**
     * Creates a plan.
     *
     * @throws ModelException
     */
    public static function makePlan(string $id): Plan
    {
        $plan = new Plan();
        $plan->id = $id;
        $plan->name = ucfirst($id);
        $plan->amount = 100;
        $plan->interval = Interval::MONTH;
        $plan->interval_count = 2;
        $plan->saveOrFail();

        return $plan;
    }
}
