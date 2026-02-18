<?php

namespace App\Tests\AccountsPayable\Models;

use App\AccountsPayable\Models\Vendor;
use App\Core\Search\Libs\SearchDocumentFactory;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\EventSpool;
use App\Tests\AppTestCase;

class VendorTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testAddress(): void
    {
        $vendor = new Vendor();
        $vendor->tenant_id = (int) self::$company->id();
        $vendor->name = 'Sherlock Holmes';
        $vendor->address1 = '221B Baker St';
        $vendor->address2 = 'Unit 1';
        $vendor->city = 'London';
        $vendor->state = 'England';
        $vendor->country = 'GB';
        $vendor->postal_code = '1234';

        $this->assertEquals('221B Baker St
Unit 1
London
1234
United Kingdom', $vendor->address);
    }

    public function testCreate(): void
    {
        EventSpool::enable();

        self::$vendor = new Vendor();
        self::$vendor->name = 'Test Vendor';
        self::$vendor->saveOrFail();

        $this->assertEquals('VEND-00001', self::$vendor->number);
    }

    /**
     * @depends testCreate
     */
    public function testEventCreated(): void
    {
        $this->assertHasEvent(self::$vendor, EventType::VendorCreated);
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'active' => true,
            'address1' => null,
            'address2' => null,
            'approval_workflow_id' => null,
            'bank_account_id' => null,
            'city' => null,
            'country' => null,
            'created_at' => self::$vendor->created_at,
            'email' => null,
            'id' => self::$vendor->id,
            'name' => 'Test Vendor',
            'network_connection_id' => null,
            'number' => 'VEND-00001',
            'object' => 'vendor',
            'postal_code' => null,
            'state' => null,
            'updated_at' => self::$vendor->updated_at,
        ];
        $this->assertEquals($expected, self::$vendor->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        EventSpool::enable();

        self::$vendor->active = false;
        $this->assertTrue(self::$vendor->save());
    }

    /**
     * @depends testEdit
     */
    public function testEventEdited(): void
    {
        $this->assertHasEvent(self::$vendor, EventType::VendorUpdated);
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        EventSpool::enable();

        $this->assertTrue(self::$vendor->delete());
    }

    /**
     * @depends testDelete
     */
    public function testEventDeleted(): void
    {
        $this->assertHasEvent(self::$vendor, EventType::VendorDeleted);
    }

    /**
     * @depends testCreate
     */
    public function testToSearchDocument(): void
    {
        $expected = [
            'name' => 'Test Vendor',
            'number' => 'VEND-00001',
            '_vendor' => self::$vendor->id(),
        ];

        $this->assertEquals($expected, (new SearchDocumentFactory())->make(self::$vendor));
    }

    public function testEventAssociations(): void
    {
        $vendor = new Vendor();

        $this->assertEquals([], $vendor->getEventAssociations());
    }

    public function testEventObject(): void
    {
        $expected = [
            'active' => false,
            'address1' => null,
            'address2' => null,
            'approval_workflow' => null,
            'approval_workflow_id' => null,
            'bank_account' => null,
            'bank_account_id' => null,
            'city' => null,
            'country' => null,
            'created_at' => self::$vendor->created_at,
            'email' => null,
            'id' => self::$vendor->id,
            'name' => 'Test Vendor',
            'network_connection' => null,
            'network_connection_id' => null,
            'number' => 'VEND-00001',
            'object' => 'vendor',
            'postal_code' => null,
            'state' => null,
            'updated_at' => self::$vendor->updated_at,
        ];
        $this->assertEquals($expected, self::$vendor->getEventObject());
    }
}
