<?php

namespace App\Tests\PaymentProcessing\Models;

use App\PaymentProcessing\Models\MerchantAccountRouting;
use App\PaymentProcessing\Models\PaymentMethod;
use App\Tests\AppTestCase;

class MerchantAccountRoutingTest extends AppTestCase
{
    private static MerchantAccountRouting $routing;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
        self::hasMerchantAccount('test');
    }

    public function testCreate(): void
    {
        self::$routing = new MerchantAccountRouting();
        self::$routing->method = PaymentMethod::CREDIT_CARD;
        self::$routing->invoice_id = (int) self::$invoice->id();
        self::$routing->merchant_account_id = (int) self::$merchantAccount->id();
        $this->assertTrue(self::$routing->save());
        $this->assertEquals(self::$company->id(), self::$routing->tenant_id);
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'id' => self::$routing->id(),
            'method' => PaymentMethod::CREDIT_CARD,
            'invoice_id' => self::$invoice->id(),
            'merchant_account_id' => self::$merchantAccount->id(),
            'created_at' => self::$routing->created_at,
            'updated_at' => self::$routing->updated_at,
        ];

        $this->assertEquals($expected, self::$routing->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testQuery(): void
    {
        $routings = MerchantAccountRouting::all();

        $this->assertCount(1, $routings);
        $this->assertEquals(self::$routing->id(), $routings[0]->id());
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$routing->delete());
    }
}
