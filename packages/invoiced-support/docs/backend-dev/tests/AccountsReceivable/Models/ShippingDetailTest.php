<?php

namespace App\Tests\AccountsReceivable\Models;

use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\ShippingDetail;
use App\Tests\AppTestCase;

class ShippingDetailTest extends AppTestCase
{
    private static ShippingDetail $detail;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
    }

    public function testCreate(): void
    {
        self::$detail = new ShippingDetail();
        $this->assertTrue(self::$detail->create([
            'invoice_id' => self::$invoice->id(),
            'country' => 'US',
        ]));

        $this->assertEquals(self::$company->id(), self::$detail->tenant_id);
    }

    /**
     * @depends testCreate
     */
    public function testAccessor(): void
    {
        $invoice = new Invoice();
        $this->assertNull($invoice->ship_to);

        /** @var ShippingDetail $detail */
        $detail = self::$invoice->ship_to;
        $this->assertInstanceOf(ShippingDetail::class, $detail);
        $this->assertEquals(self::$detail->id(), $detail->id());
    }

    /**
     * @depends testCreate
     */
    public function testQuery(): void
    {
        $details = ShippingDetail::all();

        $this->assertCount(1, $details);
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'name' => null,
            'attention_to' => null,
            'address1' => null,
            'address2' => null,
            'city' => null,
            'state' => null,
            'postal_code' => null,
            'country' => 'US',
            'created_at' => self::$detail->created_at,
            'updated_at' => self::$detail->updated_at,
        ];

        $this->assertEquals($expected, self::$detail->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$detail->delete());
    }
}
