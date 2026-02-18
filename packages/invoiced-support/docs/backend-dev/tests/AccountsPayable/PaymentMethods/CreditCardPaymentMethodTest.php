<?php

namespace App\Tests\AccountsPayable\PaymentMethods;

use App\AccountsPayable\Enums\PayableDocumentStatus;
use App\AccountsPayable\Models\Bill;
use App\AccountsPayable\Models\CompanyCard;
use App\AccountsPayable\Models\VendorPayment;
use App\AccountsPayable\Models\VendorPaymentBatch;
use App\AccountsPayable\Models\VendorPaymentBatchBill;
use App\AccountsPayable\PaymentMethods\CreditCardPaymentMethod;
use App\AccountsPayable\ValueObjects\PayVendorPayment;
use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Company;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Operations\ProcessPayment;
use App\PaymentProcessing\ValueObjects\ChargeApplication;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Mockery;
use Stripe\StripeClient;

class CreditCardPaymentMethodTest extends AppTestCase
{
    private static Company $company2;
    private static CompanyCard $companyCard;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$company2 = self::getTestDataFactory()->createCompany();
        $paymentMethod = self::getTestDataFactory()->acceptsPaymentMethod(self::$company2, PaymentMethod::CREDIT_CARD, 'stripe');
        self::hasMerchantAccount('stripe');
        $paymentMethod->setMerchantAccount(self::$merchantAccount);
        $paymentMethod->saveOrFail();
        self::hasCustomer();

        self::hasCompany();
        self::hasVendor();
        $networkConnection = self::getTestDataFactory()->connectCompanies(self::$company2, self::$company);
        self::$vendor->network_connection = $networkConnection;
        self::$vendor->saveOrFail();

        self::$companyCard = new CompanyCard();
        self::$companyCard->funding = 'credit';
        self::$companyCard->brand = 'Visa';
        self::$companyCard->last4 = '1234';
        self::$companyCard->exp_month = 2;
        self::$companyCard->exp_year = 2036;
        self::$companyCard->issuing_country = 'US';
        self::$companyCard->gateway = 'stripe';
        self::$companyCard->stripe_customer = 'cust_test';
        self::$companyCard->stripe_payment_method = 'card_test';
        self::$companyCard->saveOrFail();

        self::getService('test.tenant')->runAs(self::$company, function () use ($networkConnection) {
            self::$customer->network_connection = $networkConnection;
            self::$customer->saveOrFail();
        });
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::$company2->delete();
    }

    private function getOperation(ProcessPayment $processPayment): CreditCardPaymentMethod
    {
        return new CreditCardPaymentMethod('secret', self::getService('test.tenant'), $processPayment);
    }

    public function testPay(): void
    {
        $vendorPayment = new VendorPayment();

        $processPayment = Mockery::mock(ProcessPayment::class);

        $stripe = Mockery::mock(StripeClient::class);
        $paymentMethods = Mockery::mock();
        $paymentMethods->shouldReceive('create')
            ->withArgs([
                ['customer' => 'cust_test', 'payment_method' => 'card_test'],
                ['stripe_account' => 'TEST_MERCHANT_ID'],
            ])
            ->andReturn((object) [
                'id' => 'pm_cloned',
            ]);
        $stripe->paymentMethods = $paymentMethods;

        $operation = $this->getOperation($processPayment);
        $operation->setStripe($stripe);

        $processPayment->shouldReceive('pay')
            ->andReturnUsing(function (PaymentMethod $paymentMethod, Customer $customer, ChargeApplication $chargeApplication, array $parameters) use ($operation, $vendorPayment) {
                $this->assertEquals(self::$customer->id, $customer->id);
                $this->assertEquals(['gateway_token' => 'pm_cloned'], $parameters);
                $operation->setCreatedPayment($vendorPayment);
            })
            ->once();

        $batchPayment = self::getTestDataFactory()->createBatchPayment(null, 'credit_card', self::$companyCard);
        $payment = new PayVendorPayment(self::$vendor);
        $payment->addBatchBill($this->createBatchPaymentBill($batchPayment, 1));
        $payment->addBatchBill($this->createBatchPaymentBill($batchPayment, 2));
        $payment->addBatchBill($this->createBatchPaymentBill($batchPayment, 3));
        $payment->addBatchBill($this->createBatchPaymentBill($batchPayment, 4));

        $vendorPayment2 = $operation->pay($payment, ['card' => self::$companyCard, 'payment_batch' => $batchPayment]);

        $this->assertEquals($vendorPayment, $vendorPayment2);
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
