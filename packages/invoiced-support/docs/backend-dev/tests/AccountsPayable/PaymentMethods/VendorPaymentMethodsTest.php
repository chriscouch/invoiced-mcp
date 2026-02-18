<?php

namespace App\Tests\AccountsPayable\PaymentMethods;

use App\AccountsPayable\Models\CompanyBankAccount;
use App\AccountsPayable\Models\Vendor;
use App\AccountsPayable\Models\VendorBankAccount;
use App\AccountsPayable\PaymentMethods\VendorPaymentMethods;
use App\Companies\Models\Company;
use App\PaymentProcessing\Models\PaymentMethod;
use App\Tests\AppTestCase;

class VendorPaymentMethodsTest extends AppTestCase
{
    private static Company $company2;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$company2 = self::getTestDataFactory()->createCompany();
        $paymentMethod = self::getTestDataFactory()->acceptsPaymentMethod(self::$company2, PaymentMethod::CREDIT_CARD, 'stripe');
        $paymentMethod->convenience_fee = 300;
        $paymentMethod->saveOrFail();
        $vendorPayBankAccount = new CompanyBankAccount();
        $vendorPayBankAccount->name = 'Test';
        $vendorPayBankAccount->default = true;
        $vendorPayBankAccount->saveOrFail();

        self::hasCompany();
        $connection = self::getTestDataFactory()->connectCompanies(self::$company2, self::$company);
        self::hasVendor();
        $bankAccount = new VendorBankAccount();
        $bankAccount->vendor = self::$vendor;
        $bankAccount->bank_name = 'Chase';
        $bankAccount->last4 = '3456';
        $bankAccount->account_number = '123456';
        $bankAccount->routing_number = '012345678';
        $bankAccount->type = 'checking';
        $bankAccount->account_holder_name = 'Test';
        $bankAccount->account_holder_type = 'company';
        $bankAccount->currency = 'usd';
        $bankAccount->saveOrFail();
        self::$vendor->bank_account = $bankAccount;
        self::$vendor->network_connection = $connection;
        self::$vendor->saveOrFail();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        if (isset(self::$company2)) {
            self::$company2->delete();
        }
    }

    private function get(): VendorPaymentMethods
    {
        return new VendorPaymentMethods();
    }

    public function testGetForVendorEmpty(): void
    {
        $vendor = new Vendor();
        $methods = $this->get();
        $this->assertEquals([], $methods->getForVendor($vendor));
    }

    public function testGetForVendor(): void
    {
        $methods = $this->get();
        $expected = [
            [
                'type' => 'ach',
            ],
            [
                'type' => 'credit_card',
                'min' => null,
                'max' => null,
                'currency' => 'usd',
                'convenience_fee_percent' => 300,
            ],
        ];
        $this->assertEquals($expected, $methods->getForVendor(self::$vendor));
    }
}
