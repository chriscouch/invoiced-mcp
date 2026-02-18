<?php

namespace App\Tests\AccountsPayable\Models;

use App\AccountsPayable\Models\VendorBankAccount;
use App\Tests\AppTestCase;

class VendorBankAccountTest extends AppTestCase
{
    protected static VendorBankAccount $vendorBankAccount;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasVendor();
    }

    public function testCreate(): void
    {
        self::$vendorBankAccount = new VendorBankAccount();
        self::$vendorBankAccount->vendor = self::$vendor;
        self::$vendorBankAccount->bank_name = 'Chase';
        self::$vendorBankAccount->last4 = '3456';
        self::$vendorBankAccount->account_number = '123456';
        self::$vendorBankAccount->routing_number = '012345678';
        self::$vendorBankAccount->type = 'checking';
        self::$vendorBankAccount->account_holder_name = 'Test';
        self::$vendorBankAccount->account_holder_type = 'company';
        self::$vendorBankAccount->currency = 'usd';
        $this->assertTrue(self::$vendorBankAccount->create());
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'created_at' => self::$vendorBankAccount->created_at,
            'id' => self::$vendorBankAccount->id(),
            'last4' => '3456',
            'account_holder_name' => 'Test',
            'account_holder_type' => 'company',
            'account_number' => '123456',
            'bank_name' => 'Chase',
            'country' => 'US',
            'currency' => 'usd',
            'routing_number' => '012345678',
            'type' => 'checking',
            'vendor_id' => self::$vendor->id,
            'updated_at' => self::$vendorBankAccount->updated_at,
        ];
        $this->assertEquals($expected, self::$vendorBankAccount->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$vendorBankAccount->bank_name = 'Test';
        $this->assertTrue(self::$vendorBankAccount->save());
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$vendorBankAccount->delete());
        $this->assertFalse(self::$vendorBankAccount->persisted());
    }
}
