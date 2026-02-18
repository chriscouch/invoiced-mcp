<?php

namespace App\Tests\AccountsReceivable\Libs;

use App\Core\Cron\ValueObjects\Run;
use App\AccountsReceivable\Libs\ExpiredDiscountCleaner;
use App\AccountsReceivable\Models\Invoice;
use App\Tests\AppTestCase;
use App\Core\Orm\Query;

class ExpiredDiscountCleanerTest extends AppTestCase
{
    private static Invoice $invoiceWithExpiringDiscount;
    private static Invoice $closedInvoice;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
    }

    public function testGetExpiredDiscounts(): void
    {
        $cleaner = new ExpiredDiscountCleaner(self::getService('test.transaction_manager'));
        $query = $cleaner->getExpiredDiscounts(self::$company);

        $this->assertInstanceOf(Query::class, $query);
        $discounts = $query->all();
        $this->assertCount(0, $discounts);

        // create an invoice with a future expiring discount
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 100]];
        $invoice->discounts = [[
            'amount' => 10,
            'expires' => time() + 3600,
        ]];
        $this->assertTrue($invoice->save());
        self::$invoiceWithExpiringDiscount = $invoice;
        $this->assertEquals(90, $invoice->total);

        $invoiceDiscounts = $invoice->discounts();
        $this->assertCount(1, $invoiceDiscounts);

        // set the discount to expire in the past
        self::getService('test.database')->update('AppliedRates', ['expires' => time() - 3600], ['id' => $invoiceDiscounts[0]['id']]);

        // create a closed invoice with a future expiring discount
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 100]];
        $invoice->discounts = [[
            'amount' => 10,
            'expires' => time() - 1800,
        ]];
        $invoice->closed = true;
        $this->assertTrue($invoice->save());
        self::$closedInvoice = $invoice;

        $invoiceDiscounts2 = $invoice->discounts();
        $this->assertCount(1, $invoiceDiscounts2);

        // get the expired discounts
        $query = $cleaner->getExpiredDiscounts(self::$company);

        $this->assertInstanceOf(Query::class, $query);
        $discounts = $query->all();

        // verify our invoice discounts are returned
        $this->assertCount(2, $discounts);
        $expected = [
            $invoiceDiscounts[0]['id'],
            $invoiceDiscounts2[0]['id'],
        ];

        foreach ($discounts as $discount) {
            $k = array_search($discount->id(), $expected);
            if (false !== $k) {
                unset($expected[$k]);
            }
        }
        $this->assertCount(0, $expected);
    }

    /**
     * @depends testGetExpiredDiscounts
     */
    public function testDeleteExpired(): void
    {
        $job = self::getService('test.expired_discount_job');
        $job->execute(new Run());
        $this->assertEquals(2, $job->getTaskCount());

        self::getService('test.tenant')->set(self::$company);

        $this->assertEquals(100, self::$invoiceWithExpiringDiscount->refresh()->total);
        $this->assertCount(0, self::$invoiceWithExpiringDiscount->discounts(true));

        $discounts = self::$closedInvoice->discounts(true);
        $this->assertCount(1, $discounts);
        $this->assertNull($discounts[0]['expires']);
    }
}
