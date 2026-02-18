<?php

namespace App\Tests\SubscriptionBilling\Metrics;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Discount;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\LineItem;
use App\Core\I18n\ValueObjects\Money;
use App\SubscriptionBilling\Metrics\MrrCalculator;
use App\SubscriptionBilling\Models\Plan;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;

class MrrCalculatorTest extends AppTestCase
{
    private static MrrCalculator $calculator;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$calculator = new MrrCalculator();
    }

    public function testLineItemYearly(): void
    {
        $lineItem = new LineItem([
            'amount' => 100,
        ]);
        $plan = new Plan([
            'interval' => 'year',
            'interval_count' => 1,
        ]);
        $discounts = new Money('usd', 0);
        $invoice = new Invoice([
            'currency' => 'usd',
        ]);

        $this->assertEquals([
            new Money('usd', 833), // MRR
            new Money('usd', 0), // discount
        ], self::$calculator->calculateForLineItem($lineItem, $plan, $invoice, $discounts));
    }

    public function testLineItemMonthly(): void
    {
        $lineItem = new LineItem([
            'amount' => 100,
        ]);
        $plan = new Plan([
            'interval' => 'month',
            'interval_count' => 1,
        ]);
        $discounts = new Money('usd', 0);
        $invoice = new Invoice([
            'currency' => 'usd',
        ]);

        $this->assertEquals([
            new Money('usd', 10000), // MRR
            new Money('usd', 0), // discount
        ], self::$calculator->calculateForLineItem($lineItem, $plan, $invoice, $discounts));
    }

    public function testLineItemWeekly(): void
    {
        $lineItem = new LineItem([
            'amount' => 100,
        ]);
        $plan = new Plan([
            'interval' => 'week',
            'interval_count' => 1,
        ]);
        $discounts = new Money('usd', 0);
        $invoice = new Invoice([
            'currency' => 'usd',
        ]);

        $this->assertEquals([
            new Money('usd', 43333), // MRR
            new Money('usd', 0), // discount
        ], self::$calculator->calculateForLineItem($lineItem, $plan, $invoice, $discounts));
    }

    public function testLineItemDaily(): void
    {
        $lineItem = new LineItem([
            'amount' => 100,
        ]);
        $plan = new Plan([
            'interval' => 'day',
            'interval_count' => 5,
        ]);
        $discounts = new Money('usd', 0);
        $invoice = new Invoice([
            'currency' => 'usd',
        ]);

        $this->assertEquals([
            new Money('usd', 60833), // MRR
            new Money('usd', 0), // discount
        ], self::$calculator->calculateForLineItem($lineItem, $plan, $invoice, $discounts));
    }

    public function testLineItemProration(): void
    {
        $lineItem = new LineItem([
            'amount' => 100,
            'prorated' => true,
            'period_start' => (new CarbonImmutable('2023-07-05'))->startOfDay()->getTimestamp(),
            'period_end' => (new CarbonImmutable('2023-07-31'))->endOfDay()->getTimestamp(),
        ]);
        $plan = new Plan([
            'interval' => 'month',
            'interval_count' => 1,
        ]);
        $discounts = new Money('usd', 50);
        $invoice = new Invoice([
            'currency' => 'usd',
            'subtotal' => 100,
        ]);

        $this->assertEquals([
            new Money('usd', 8666), // MRR
            new Money('usd', 44), // discount
        ], self::$calculator->calculateForLineItem($lineItem, $plan, $invoice, $discounts));
    }

    public function testLineItemDiscounted(): void
    {
        $lineItem = new LineItem([
            'amount' => 100,
            'discounts' => [
                new Discount(['amount' => 5]),
            ],
        ]);
        $plan = new Plan([
            'interval' => 'month',
            'interval_count' => 1,
        ]);
        $discounts = new Money('usd', 100);
        $invoice = new Invoice([
            'currency' => 'usd',
            'subtotal' => 200,
        ]);

        $this->assertEquals([
            new Money('usd', 9450), // MRR
            new Money('usd', 550), // discount
        ], self::$calculator->calculateForLineItem($lineItem, $plan, $invoice, $discounts));
    }

    public function testLineItemCreditNote(): void
    {
        $lineItem = new LineItem([
            'amount' => 100,
        ]);
        $plan = new Plan([
            'interval' => 'month',
            'interval_count' => 1,
        ]);
        $discounts = new Money('usd', 50);
        $creditNote = new CreditNote([
            'currency' => 'usd',
            'subtotal' => 100,
        ]);

        $this->assertEquals([
            new Money('usd', -9950), // MRR
            new Money('usd', -50), // discount
        ], self::$calculator->calculateForLineItem($lineItem, $plan, $creditNote, $discounts));
    }
}
