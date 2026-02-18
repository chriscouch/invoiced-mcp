<?php

namespace App\Tests\PaymentProcessing\Models;

use App\Core\Utils\Enums\ObjectType;
use App\PaymentProcessing\Models\DisabledPaymentMethod;
use App\PaymentProcessing\Models\PaymentMethod;
use App\Tests\AppTestCase;

class DisabledPaymentMethodTest extends AppTestCase
{
    private static DisabledPaymentMethod $disabled;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
    }

    public function testCreate(): void
    {
        self::$disabled = new DisabledPaymentMethod();
        self::$disabled->object_type = ObjectType::Customer->typeName();
        self::$disabled->object_id = (string) self::$customer->id();
        self::$disabled->method = PaymentMethod::CREDIT_CARD;
        $this->assertTrue(self::$disabled->save());
        $this->assertEquals(self::$company->id(), self::$disabled->tenant_id);
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'id' => self::$disabled->id(),
            'object_type' => 'customer',
            'object_id' => self::$customer->id(),
            'method' => PaymentMethod::CREDIT_CARD,
            'created_at' => self::$disabled->created_at,
            'updated_at' => self::$disabled->updated_at,
        ];

        $this->assertEquals($expected, self::$disabled->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testQuery(): void
    {
        $methods = DisabledPaymentMethod::all();

        $this->assertCount(1, $methods);
        $this->assertEquals(self::$disabled->id(), $methods[0]->id());
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$disabled->delete());
    }
}
