<?php

namespace App\Tests\AccountsPayable\Operations;

use App\AccountsPayable\Models\VendorPaymentBatchBill;
use App\AccountsPayable\Operations\PayVendor;
use App\AccountsPayable\ValueObjects\PayVendorPayment;
use App\PaymentProcessing\Models\PaymentMethod;
use App\Tests\AppTestCase;

class PayVendorTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();

        self::hasCompanyBankAccount();
        self::$companyBankAccount->saveOrFail();
        self::hasVendor();
        self::$vendor->address1 = 'address1';
        self::$vendor->address2 = 'address2';
        self::$vendor->city = 'city';
        self::$vendor->state = 'TX';
        self::$vendor->postal_code = '78738';
        self::$vendor->country = 'US';
        self::$vendor->saveOrFail();
        self::hasBill();
    }

    private function get(): PayVendor
    {
        return self::getService('test.pay_vendor');
    }

    public function testCreate(): void
    {
        $operation = $this->get();
        $item = new PayVendorPayment(self::$vendor);
        $bill = new VendorPaymentBatchBill();
        $bill->vendor = self::$vendor;
        $bill->bill = self::$bill;
        $bill->amount = 100;
        $item->addBatchBill($bill);
        $vendorPayment = $operation->pay('print_check', $item, ['bank_account' => self::$companyBankAccount]);

        $this->assertEquals(PaymentMethod::CHECK, $vendorPayment->payment_method);
    }
}
