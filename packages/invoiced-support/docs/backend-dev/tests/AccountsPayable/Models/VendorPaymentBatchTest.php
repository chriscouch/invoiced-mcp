<?php

namespace App\Tests\AccountsPayable\Models;

use App\AccountsPayable\Enums\VendorBatchPaymentStatus;
use App\AccountsPayable\Enums\CheckStock;
use App\AccountsPayable\Models\VendorPaymentBatch;
use App\AccountsPayable\Models\VendorPaymentBatchBill;
use App\ActivityLog\Libs\EventSpool;
use App\Tests\AppTestCase;

class VendorPaymentBatchTest extends AppTestCase
{
    private static VendorPaymentBatch $vendorPaymentBatch;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasVendor();
        self::hasBill();
        self::hasCompanyBankAccount();
    }

    public function testCreate(): void
    {
        EventSpool::enable();

        self::$vendorPaymentBatch = new VendorPaymentBatch();
        self::$vendorPaymentBatch->total = 100;
        self::$vendorPaymentBatch->currency = 'usd';
        self::$vendorPaymentBatch->payment_method = 'print_check';
        self::$vendorPaymentBatch->check_layout = CheckStock::CheckOnTop;
        self::$vendorPaymentBatch->initial_check_number = 1;
        self::$vendorPaymentBatch->bank_account = self::$companyBankAccount;
        self::$vendorPaymentBatch->saveOrFail();

        $this->assertEquals(self::$company->id, self::$vendorPaymentBatch->tenant_id);
        $this->assertEquals('BAT-00001', self::$vendorPaymentBatch->number);

        // create some items
        $item1 = new VendorPaymentBatchBill();
        $item1->vendor_payment_batch = self::$vendorPaymentBatch;
        $item1->amount = 50;
        $item1->bill = self::$bill;
        $item1->saveOrFail();
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'bank_account_id' => self::$companyBankAccount->id,
            'card_id' => null,
            'check_layout' => 'CheckOnTop',
            'created_at' => self::$vendorPaymentBatch->created_at,
            'currency' => 'usd',
            'id' => self::$vendorPaymentBatch->id,
            'initial_check_number' => 1,
            'member_id' => null,
            'name' => 'Payment Batch',
            'number' => 'BAT-00001',
            'payment_method' => 'print_check',
            'status' => 'Created',
            'total' => 100.0,
            'updated_at' => self::$vendorPaymentBatch->updated_at,
        ];
        $this->assertEquals($expected, self::$vendorPaymentBatch->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$vendorPaymentBatch->total = 200;
        $this->assertTrue(self::$vendorPaymentBatch->save());
    }

    /**
     * @depends testCreate
     */
    public function testVoid(): void
    {
        self::getService('test.vendor_payment_batch_void')->void(self::$vendorPaymentBatch);
        $this->assertTrue(self::$vendorPaymentBatch->persisted());
        $this->assertEquals(VendorBatchPaymentStatus::Voided, self::$vendorPaymentBatch->status);
    }
}
