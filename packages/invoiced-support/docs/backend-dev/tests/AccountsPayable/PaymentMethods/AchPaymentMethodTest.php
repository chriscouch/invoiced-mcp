<?php

namespace App\Tests\AccountsPayable\PaymentMethods;

use App\AccountsPayable\Enums\PayableDocumentStatus;
use App\AccountsPayable\Models\Bill;
use App\AccountsPayable\Models\VendorBankAccount;
use App\AccountsPayable\Models\VendorPaymentBatch;
use App\AccountsPayable\Models\VendorPaymentBatchBill;
use App\AccountsPayable\PaymentMethods\AchPaymentMethod;
use App\AccountsPayable\ValueObjects\PayVendorPayment;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;

class AchPaymentMethodTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasVendor();
        self::hasCompanyBankAccount();

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
        self::$vendor->saveOrFail();
    }

    private function getOperation(): AchPaymentMethod
    {
        return new AchPaymentMethod(self::getService('test.vendor_payment_create'));
    }

    public function testPay(): void
    {
        $operation = $this->getOperation();

        $batchPayment = self::getTestDataFactory()->createBatchPayment(self::$companyBankAccount, 'ach');
        $payment = new PayVendorPayment(self::$vendor);
        $payment->addBatchBill($this->createBatchPaymentBill($batchPayment, 1));
        $payment->addBatchBill($this->createBatchPaymentBill($batchPayment, 2));
        $payment->addBatchBill($this->createBatchPaymentBill($batchPayment, 3));
        $payment->addBatchBill($this->createBatchPaymentBill($batchPayment, 4));

        $vendorPayment = $operation->pay($payment, ['bank_account' => self::$companyBankAccount, 'payment_batch' => $batchPayment]);

        $this->assertEquals(10, $vendorPayment['amount']);
        $this->assertEquals('ach', $vendorPayment['payment_method']);
        $this->assertEquals($batchPayment->id, $vendorPayment->vendor_payment_batch_id);
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
