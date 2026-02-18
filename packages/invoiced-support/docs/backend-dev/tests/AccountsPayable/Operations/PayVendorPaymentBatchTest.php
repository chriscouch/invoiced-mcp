<?php

namespace App\Tests\AccountsPayable\Operations;

use App\AccountsPayable\Enums\VendorBatchPaymentStatus;
use App\AccountsPayable\Models\VendorPayment;
use App\AccountsPayable\Models\VendorPaymentBatch;
use App\AccountsPayable\Operations\PayVendorPaymentBatch;
use App\Tests\AppTestCase;

class PayVendorPaymentBatchTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();

        self::hasCompanyBankAccount();
        self::$companyBankAccount->saveOrFail();
        self::hasBatchPayment();
        self::hasVendor();
        self::$vendor->address1 = 'address1';
        self::$vendor->address2 = 'address2';
        self::$vendor->city = 'city';
        self::$vendor->state = 'TX';
        self::$vendor->postal_code = '78738';
        self::$vendor->country = 'US';
        self::$vendor->saveOrFail();
        self::hasBill();
        self::hasBatchPaymentBill();
    }

    private function get(): PayVendorPaymentBatch
    {
        return self::getService('test.pay_vendor_payment_batch');
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testCreateAlreadyFinished(): void
    {
        $batchPayment = new VendorPaymentBatch(['status' => VendorBatchPaymentStatus::Finished]);
        $operation = $this->get();
        $operation->pay($batchPayment);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testCreateAlreadyVoided(): void
    {
        $batchPayment = new VendorPaymentBatch(['status' => VendorBatchPaymentStatus::Voided]);
        $operation = $this->get();
        $operation->pay($batchPayment);
    }

    public function testCreate(): void
    {
        $operation = $this->get();
        $operation->pay(self::$batchPayment);

        $this->assertEquals(1, VendorPayment::where('vendor_payment_batch_id', self::$batchPayment)->count());
        $this->assertEquals(VendorBatchPaymentStatus::Finished, self::$batchPayment->status);
    }
}
