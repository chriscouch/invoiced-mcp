<?php

namespace App\Tests\Companies\Libs;

use App\AccountsReceivable\Enums\PaymentLinkStatus;
use App\AccountsReceivable\Models\Coupon;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\Item;
use App\AccountsReceivable\Models\PaymentLink;
use App\AccountsReceivable\Models\PaymentLinkItem;
use App\AccountsReceivable\Models\PaymentLinkSession;
use App\CashApplication\Enums\PaymentItemIntType;
use App\Chasing\Models\LateFeeSchedule;
use App\Companies\Libs\CompanyReset;
use App\Companies\Models\AutoNumberSequence;
use App\Companies\Models\Company;
use App\Core\Entitlements\Models\Product;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Utils\RandomString;
use App\Integrations\Flywire\Enums\FlywireRefundStatus;
use App\Integrations\Flywire\Models\FlywireRefund;
use App\Integrations\Flywire\Models\FlywireRefundBundle;
use App\PaymentProcessing\Enums\PaymentFlowSource;
use App\PaymentProcessing\Enums\PaymentFlowStatus;
use App\PaymentProcessing\Enums\TokenizationFlowStatus;
use App\PaymentProcessing\Models\PaymentFlow;
use App\PaymentProcessing\Models\PaymentFlowApplication;
use App\PaymentProcessing\Models\TokenizationFlow;
use App\SalesTax\Models\TaxRate;
use App\SubscriptionBilling\Models\Plan;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;

class CompanyResetTest extends AppTestCase
{
    private static Company $testCompany;

    public static function setUpBeforeClass(): void
    {
        self::hasCompany();
        self::getService('test.tenant')->clear();

        self::$testCompany = new Company();
        self::$testCompany->name = 'TEST MODE';
        self::$testCompany->username = 'testmode'.time();
        self::$testCompany->test_mode = true;
        self::$testCompany->currency = 'usd';
        self::$testCompany->saveOrFail();
        self::getService('test.product_installer')->install(Product::where('name', 'Accounts Receivable Free')->one(), self::$testCompany);
        self::getService('test.product_installer')->install(Product::where('name', 'Accounts Payable Free')->one(), self::$testCompany);
        self::getService('test.product_installer')->install(Product::where('name', 'Subscription Billing')->one(), self::$testCompany);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::$testCompany->delete();
    }

    private function getReset(): CompanyReset
    {
        return self::getService('test.company_reset');
    }

    public function testClearData(): void
    {
        self::getService('test.tenant')->set(self::$testCompany);

        self::hasCustomer();
        self::hasInvoice();
        self::hasVendor();
        self::hasCompanyBankAccount();
        self::hasBill();
        self::hasBatchPayment();
        self::hasBatchPaymentBill();

        $bundle = new FlywireRefundBundle();
        $bundle->bundle_id = 'ABC123';
        $bundle->recipient_id = 'UUO';
        $bundle->initiated_at = CarbonImmutable::now();
        $bundle->setAmount(new Money('USD', 100));
        $bundle->saveOrFail();

        $refund = new FlywireRefund();
        $refund->refund_id = 'ABC1234';
        $refund->recipient_id = 'UUO';
        $refund->initiated_at = CarbonImmutable::now();
        $refund->setAmount(new Money('USD', 100));
        $refund->setAmountTo(new Money('USD', 100));
        $refund->bundle = $bundle;
        $refund->status = FlywireRefundStatus::Initiated;
        $refund->saveOrFail();

        $paymentLink = new PaymentLink();
        $paymentLink->status = PaymentLinkStatus::Active;
        $paymentLink->reusable = true;
        $paymentLink->currency = 'usd';
        $paymentLink->customer = self::$customer;
        $paymentLink->saveOrFail();

        $session = new PaymentLinkSession();
        $session->hash = '';
        $session->payment_link = $paymentLink;
        $session->customer = self::$customer;
        $session->invoice = self::$invoice;
        $session->completed_at = CarbonImmutable::now();
        $session->save();

        $tokenizationFlow = new TokenizationFlow();
        $tokenizationFlow->identifier = RandomString::generate();
        $tokenizationFlow->status = TokenizationFlowStatus::CollectPaymentDetails;
        $tokenizationFlow->initiated_from = PaymentFlowSource::Api;
        $tokenizationFlow->customer = self::$customer;
        $tokenizationFlow->saveOrFail();

        $item1 = new PaymentLinkItem();
        $item1->payment_link = $paymentLink;
        $item1->description = 'Item 1';
        $item1->amount = 100;
        $item1->saveOrFail();

        $paymentFlow = new PaymentFlow();
        $paymentFlow->identifier = RandomString::generate();
        $paymentFlow->status = PaymentFlowStatus::CollectPaymentDetails;
        $paymentFlow->initiated_from = PaymentFlowSource::Api;
        $paymentFlow->currency = 'usd';
        $paymentFlow->amount = self::$invoice->balance;
        $paymentFlow->customer = self::$customer;
        $paymentFlow->saveOrFail();

        $item1 = new PaymentFlowApplication();
        $item1->payment_flow = $paymentFlow;
        $item1->type = PaymentItemIntType::Invoice;
        $item1->amount = 100;
        $item1->saveOrFail();

        $this->getReset()->clearData(self::$testCompany);
        $this->assertNull(Customer::find(self::$customer->id()));
        $this->assertNull(Invoice::find(self::$invoice->id()));

        $sequence = AutoNumberSequence::findOrFail([self::$testCompany->id(), 'customer']);
        $this->assertEquals(1, $sequence->next);
        $sequence = AutoNumberSequence::findOrFail([self::$testCompany->id(), 'invoice']);
        $this->assertEquals(1, $sequence->next);
        $sequence = AutoNumberSequence::findOrFail([self::$testCompany->id(), 'estimate']);
        $this->assertEquals(1, $sequence->next);
        $sequence = AutoNumberSequence::findOrFail([self::$testCompany->id(), 'credit_note']);
        $this->assertEquals(1, $sequence->next);
    }

    public function testClearDataFail(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->getReset()->clearData(self::$company);
    }

    public function testClearSettings(): void
    {
        self::getService('test.tenant')->set(self::$testCompany);

        self::hasPlan();
        self::hasItem();
        self::hasLateFeeSchedule();
        self::hasCoupon();
        self::hasTaxRate();

        $this->getReset()->clearSettings(self::$testCompany);

        $this->assertEquals(0, Plan::count());
        $this->assertEquals(0, Item::count());
        $this->assertEquals(0, LateFeeSchedule::count());
        $this->assertEquals(0, Coupon::count());
        $this->assertEquals(0, TaxRate::count());
    }

    public function testClearSettingsFail(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->getReset()->clearSettings(self::$company);
    }
}
