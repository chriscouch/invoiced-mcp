<?php

namespace App\Tests\AccountsPayable\PaymentMethods;

use App\AccountsPayable\Enums\PayableDocumentStatus;
use App\AccountsPayable\Models\Bill;
use App\AccountsPayable\Models\VendorPaymentBatch;
use App\AccountsPayable\Models\VendorPaymentBatchBill;
use App\AccountsPayable\PaymentMethods\PrintCheckPaymentMethod;
use App\AccountsPayable\ValueObjects\PayVendorPayment;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;

class PrintCheckPaymentMethodTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasVendor();
        self::hasCompanyBankAccount();
    }

    private function getOperation(): PrintCheckPaymentMethod
    {
        return new PrintCheckPaymentMethod(self::getService('test.vendor_payment_create'));
    }

    public function testPay(): void
    {
        $operation = $this->getOperation();

        $batchPayment = self::getTestDataFactory()->createBatchPayment(self::$companyBankAccount);
        $payment = new PayVendorPayment(self::$vendor);
        $payment->addBatchBill($this->createBatchPaymentBill($batchPayment, 1));
        $payment->addBatchBill($this->createBatchPaymentBill($batchPayment, 2));
        $payment->addBatchBill($this->createBatchPaymentBill($batchPayment, 3));
        $payment->addBatchBill($this->createBatchPaymentBill($batchPayment, 4));

        $vendorPayment = $operation->pay($payment, ['bank_account' => self::$companyBankAccount, 'payment_batch' => $batchPayment, 'check_number' => 1]);

        $this->assertEquals(10, $vendorPayment['amount']);
        $this->assertEquals('check', $vendorPayment['payment_method']);
        $this->assertEquals('1', $vendorPayment['reference']);
        $this->assertEquals($batchPayment->id, $vendorPayment->vendor_payment_batch_id);
        $this->assertEquals(2, self::$companyBankAccount->refresh()->check_number);

        // Pay a second item in the batch
        $payment = new PayVendorPayment(self::$vendor);
        $payment->addBatchBill($this->createBatchPaymentBill($batchPayment, 5));

        $vendorPayment = $operation->pay($payment, ['bank_account' => self::$companyBankAccount, 'payment_batch' => $batchPayment, 'check_number' => 2]);

        $this->assertEquals(5, $vendorPayment['amount']);
        $this->assertEquals('check', $vendorPayment['payment_method']);
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
