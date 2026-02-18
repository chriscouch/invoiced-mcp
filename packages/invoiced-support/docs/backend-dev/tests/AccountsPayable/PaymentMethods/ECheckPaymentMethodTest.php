<?php

namespace App\Tests\AccountsPayable\PaymentMethods;

use App\AccountsPayable\Enums\PayableDocumentStatus;
use App\AccountsPayable\Models\Bill;
use App\AccountsPayable\Models\CompanyBankAccount;
use App\AccountsPayable\Models\VendorPaymentBatch;
use App\AccountsPayable\Models\VendorPaymentBatchBill;
use App\AccountsPayable\PaymentMethods\ECheckPaymentMethod;
use App\AccountsPayable\ValueObjects\PayVendorPayment;
use App\Companies\Models\Company;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;

class ECheckPaymentMethodTest extends AppTestCase
{
    private static Company $company2;
    private static CompanyBankAccount $receiverBankAccount;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$company2 = self::getTestDataFactory()->createCompany();
        self::$receiverBankAccount = self::getTestDataFactory()->createCompanyBankAccount();
        self::$receiverBankAccount->default = true;
        self::$receiverBankAccount->saveOrFail();
        self::hasCompany();
        self::hasVendor();
        $networkConnection = self::getTestDataFactory()->connectCompanies(self::$company2, self::$company);
        self::$vendor->network_connection = $networkConnection;
        self::$vendor->saveOrFail();
        self::hasBill();
        self::hasCompanyBankAccount();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::$company2->delete();
    }

    private function get(): ECheckPaymentMethod
    {
        return new ECheckPaymentMethod(self::getService('test.create_echeck'));
    }

    public function testPay(): void
    {
        $operation = $this->get();

        $batchPayment = self::getTestDataFactory()->createBatchPayment(self::$companyBankAccount, 'echeck');
        $payment = new PayVendorPayment(self::$vendor);
        $payment->addBatchBill($this->createBatchPaymentBill($batchPayment, 100));

        $vendorPayment = $operation->pay($payment, ['bank_account' => self::$companyBankAccount, 'payment_batch' => $batchPayment, 'check_number' => 1]);

        $this->assertEquals(100, $vendorPayment['amount']);
        $this->assertEquals('echeck', $vendorPayment['payment_method']);
        $this->assertEquals('1', $vendorPayment['reference']);
        $this->assertEquals($batchPayment->id, $vendorPayment->vendor_payment_batch_id);
        $this->assertEquals(2, self::$companyBankAccount->refresh()->check_number);

        // Pay a second item in the batch
        $payment = new PayVendorPayment(self::$vendor);
        $payment->addBatchBill($this->createBatchPaymentBill($batchPayment, 5));

        $vendorPayment = $operation->pay($payment, ['bank_account' => self::$companyBankAccount, 'payment_batch' => $batchPayment, 'check_number' => 2]);

        $this->assertEquals(5, $vendorPayment['amount']);
        $this->assertEquals('echeck', $vendorPayment['payment_method']);
        $this->assertEquals('2', $vendorPayment['reference']);
        $this->assertEquals($batchPayment->id, $vendorPayment->vendor_payment_batch_id);
        $this->assertEquals(3, self::$companyBankAccount->refresh()->check_number);
    }

    private function createBatchPaymentBill(VendorPaymentBatch $batchPayment, int $amount): VendorPaymentBatchBill
    {
        $bill = new Bill();
        $bill->vendor = self::$vendor;
        $bill->number = 'INV-'.uniqid();
        $bill->date = CarbonImmutable::now();
        $bill->currency = 'usd';
        $bill->total = $amount;
        $bill->status = PayableDocumentStatus::PendingApproval;
        $bill->saveOrFail();

        $batchPaymentBill = new VendorPaymentBatchBill();
        $batchPaymentBill->vendor_payment_batch = $batchPayment;
        $batchPaymentBill->bill_number = $bill->number;
        $batchPaymentBill->vendor = self::$vendor;
        $batchPaymentBill->amount = $amount;
        $batchPaymentBill->bill = $bill;
        $batchPaymentBill->saveOrFail();

        return $batchPaymentBill;
    }
}
