<?php

namespace App\Tests\AccountsPayable\Models;

use App\AccountsPayable\Models\Vendor;
use App\AccountsPayable\Models\VendorAdjustment;
use App\Core\Utils\ModelNormalizer;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\EventSpool;
use App\Tests\AppTestCase;

class VendorAdjustmentTest extends AppTestCase
{
    private static VendorAdjustment $vendorAdjustment;

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

        self::$vendorAdjustment = new VendorAdjustment();
        self::$vendorAdjustment->vendor = self::$vendor;
        self::$vendorAdjustment->bill = self::$bill;
        self::$vendorAdjustment->amount = 100;
        self::$vendorAdjustment->currency = 'usd';
        self::$vendorAdjustment->saveOrFail();

        $this->assertEquals(self::$company->id, self::$vendorAdjustment->tenant_id);
        $this->assertEquals(date('Y-m-d'), self::$vendorAdjustment->date->format('Y-m-d'));
    }

    /**
     * @depends testCreate
     */
    public function testEventCreated(): void
    {
        $this->assertHasEvent(self::$vendorAdjustment, EventType::VendorAdjustmentCreated);
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'amount' => 100.0,
            'created_at' => self::$vendorAdjustment->created_at,
            'currency' => 'usd',
            'date' => self::$vendorAdjustment->date,
            'id' => self::$vendorAdjustment->id,
            'bill_id' => self::$bill->id,
            'notes' => null,
            'object' => 'vendor_adjustment',
            'updated_at' => self::$vendorAdjustment->updated_at,
            'vendor_id' => self::$vendor->id,
            'vendor_credit_id' => null,
            'voided' => false,
        ];
        $this->assertEquals($expected, self::$vendorAdjustment->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        EventSpool::enable();

        self::$vendorAdjustment->amount = 200;
        $this->assertTrue(self::$vendorAdjustment->save());
    }

    /**
     * @depends testEdit
     */
    public function testEventEdited(): void
    {
        $this->assertHasEvent(self::$vendorAdjustment, EventType::VendorAdjustmentUpdated);
    }

    /**
     * @depends testCreate
     */
    public function testVoid(): void
    {
        EventSpool::enable();

        self::getService('test.vendor_adjustment_void')->void(self::$vendorAdjustment);
        $this->assertTrue(self::$vendorAdjustment->persisted());
        $this->assertTrue(self::$vendorAdjustment->voided);
        $this->assertNotNull(self::$vendorAdjustment->date_voided);
    }

    /**
     * @depends testVoid
     */
    public function testEventDeleted(): void
    {
        $this->assertHasEvent(self::$vendorAdjustment, EventType::VendorAdjustmentDeleted);
    }

    public function testEventAssociations(): void
    {
        $vendorAdjustment = new VendorAdjustment();
        $vendorAdjustment->vendor = new Vendor(['id' => -1]);

        $this->assertEquals([['vendor', -1]], $vendorAdjustment->getEventAssociations());
    }

    public function testEventObject(): void
    {
        $expected = [
            'amount' => 200.0,
            'created_at' => self::$vendorAdjustment->created_at,
            'currency' => 'usd',
            'date' => self::$vendorAdjustment->date->format('Y-m-d'),
            'id' => self::$vendorAdjustment->id,
            'bill_id' => self::$bill->id,
            'bill' => self::$bill->id,
            'notes' => null,
            'object' => 'vendor_adjustment',
            'updated_at' => self::$vendorAdjustment->updated_at,
            'vendor_id' => self::$vendor->id,
            'vendor' => ModelNormalizer::toArray(self::$vendor),
            'vendor_credit_id' => null,
            'vendor_credit' => null,
            'voided' => true,
        ];
        $this->assertEquals($expected, self::$vendorAdjustment->getEventObject());
    }
}
