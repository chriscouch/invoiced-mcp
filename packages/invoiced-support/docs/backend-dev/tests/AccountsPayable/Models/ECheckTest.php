<?php

namespace App\Tests\AccountsPayable\Models;

use App\AccountsPayable\Models\CompanyBankAccount;
use App\AccountsPayable\Models\ECheck;
use App\AccountsPayable\Models\VendorPayment;
use App\Tests\AppTestCase;

class ECheckTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testCreateWithoutAddress(): void
    {
        self::hasVendor();
        $vendorPayment = new VendorPayment();
        $vendorPayment->vendor = self::$vendor;
        $vendorPayment->payment_method = 'check';
        $vendorPayment->currency = 'usd';
        $vendorPayment->amount = 100;
        $vendorPayment->saveOrFail();
        $bankAccount = new CompanyBankAccount();
        $bankAccount->name = 'Test Account';
        $bankAccount->saveOrFail();

        $eCheck = new ECheck();
        $eCheck->hash = 'test';
        $eCheck->payment = $vendorPayment;
        $eCheck->account = $bankAccount;
        $eCheck->email = 'test@test.com';
        $eCheck->country = null;
        $eCheck->amount = 100;
        $eCheck->check_number = 3;
        $this->assertTrue($eCheck->save());
    }
}
