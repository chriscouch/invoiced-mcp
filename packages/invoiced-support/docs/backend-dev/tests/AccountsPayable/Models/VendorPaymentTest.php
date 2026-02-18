<?php

namespace App\Tests\AccountsPayable\Models;

use App\AccountsPayable\Models\Vendor;
use App\AccountsPayable\Models\VendorPayment;
use App\AccountsPayable\Models\VendorPaymentItem;
use App\Core\Utils\ModelNormalizer;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\EventSpool;
use App\Tests\AppTestCase;

class VendorPaymentTest extends AppTestCase
{
    private static VendorPayment $vendorPayment;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasVendor();
        self::hasBill();
    }

    public function testCreate(): void
    {
        EventSpool::enable();

        self::$vendorPayment = new VendorPayment();
        self::$vendorPayment->vendor = self::$vendor;
        self::$vendorPayment->amount = 100;
        self::$vendorPayment->currency = 'usd';
        self::$vendorPayment->saveOrFail();

        $this->assertEquals(self::$company->id, self::$vendorPayment->tenant_id);
        $this->assertEquals('other', self::$vendorPayment->payment_method);
        $this->assertEquals(date('Y-m-d'), self::$vendorPayment->date->format('Y-m-d'));

        // create some items
        $item1 = new VendorPaymentItem();
        $item1->vendor_payment = self::$vendorPayment;
        $item1->amount = 50;
        $item1->bill = self::$bill;
        $item1->saveOrFail();

        $item2 = new VendorPaymentItem();
        $item2->vendor_payment = self::$vendorPayment;
        $item2->amount = 50;
        $item2->bill = self::$bill;
        $item2->saveOrFail();
    }

    /**
     * @depends testCreate
     */
    public function testEventCreated(): void
    {
        $this->assertHasEvent(self::$vendorPayment, EventType::VendorPaymentCreated);
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'amount' => 100.0,
            'bank_account_id' => null,
            'card_id' => null,
            'created_at' => self::$vendorPayment->created_at,
            'currency' => 'usd',
            'date' => self::$vendorPayment->date,
            'expected_arrival_date' => null,
            'id' => self::$vendorPayment->id,
            'notes' => null,
            'number' => 'PAY-00001',
            'object' => 'vendor_payment',
            'payment_method' => 'other',
            'reference' => null,
            'updated_at' => self::$vendorPayment->updated_at,
            'vendor_id' => self::$vendor->id,
            'vendor_payment_batch_id' => null,
            'voided' => false,
        ];
        $this->assertEquals($expected, self::$vendorPayment->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        EventSpool::enable();

        self::$vendorPayment->amount = 200;
        $this->assertTrue(self::$vendorPayment->save());
    }

    /**
     * @depends testEdit
     */
    public function testEventEdited(): void
    {
        $this->assertHasEvent(self::$vendorPayment, EventType::VendorPaymentUpdated);
    }

    /**
     * @depends testCreate
     */
    public function testVoid(): void
    {
        EventSpool::enable();

        self::getService('test.vendor_payment_void')->void(self::$vendorPayment);
        $this->assertTrue(self::$vendorPayment->persisted());
        $this->assertTrue(self::$vendorPayment->voided);
        $this->assertNotNull(self::$vendorPayment->date_voided);
    }

    /**
     * @depends testVoid
     */
    public function testEventDeleted(): void
    {
        $this->assertHasEvent(self::$vendorPayment, EventType::VendorPaymentDeleted);
    }

    public function testEventAssociations(): void
    {
        $vendorPayment = new VendorPayment();
        $vendorPayment->vendor = new Vendor(['id' => -1]);

        $this->assertEquals([['vendor', -1]], $vendorPayment->getEventAssociations());
    }

    public function testEventObject(): void
    {
        $expected = [
            'amount' => 200.0,
            'bank_account' => null,
            'bank_account_id' => null,
            'card' => null,
            'card_id' => null,
            'created_at' => self::$vendorPayment->created_at,
            'currency' => 'usd',
            'date' => self::$vendorPayment->date->format('Y-m-d'),
            'expected_arrival_date' => null,
            'id' => self::$vendorPayment->id,
            'notes' => null,
            'number' => 'PAY-00001',
            'object' => 'vendor_payment',
            'payment_method' => 'other',
            'reference' => null,
            'updated_at' => self::$vendorPayment->updated_at,
            'vendor' => ModelNormalizer::toArray(self::$vendor),
            'vendor_id' => self::$vendor->id,
            'vendor_payment_batch' => null,
            'vendor_payment_batch_id' => null,
            'voided' => true,
        ];
        $this->assertEquals($expected, self::$vendorPayment->getEventObject());
    }
}
