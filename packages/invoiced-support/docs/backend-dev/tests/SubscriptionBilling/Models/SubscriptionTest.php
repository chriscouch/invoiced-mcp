<?php

namespace App\Tests\SubscriptionBilling\Models;

use App\AccountsReceivable\Enums\InvoiceStatus;
use App\AccountsReceivable\Models\Coupon;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\ShippingDetail;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\EventSpool;
use App\CashApplication\Models\Transaction;
use App\Companies\Models\Member;
use App\Companies\Models\Role;
use App\Core\Orm\ACLModelRequester;
use App\Core\Orm\Model;
use App\Core\Search\Libs\SearchDocumentFactory;
use App\Core\Utils\Enums\ObjectType;
use App\Core\Utils\ModelNormalizer;
use App\Core\Utils\ValueObjects\Interval;
use App\Metadata\Models\CustomField;
use App\PaymentProcessing\Gateways\MockGateway;
use App\PaymentProcessing\Gateways\TestGateway;
use App\PaymentProcessing\Models\PaymentMethod;
use App\SalesTax\Models\TaxRate;
use App\Sending\Email\Models\EmailTemplate;
use App\Sending\Email\Models\EmailTemplateOption;
use App\SubscriptionBilling\BillingMode\BillInAdvance;
use App\SubscriptionBilling\BillingMode\BillInArrears;
use App\SubscriptionBilling\EmailVariables\SubscriptionEmailVariables;
use App\SubscriptionBilling\Exception\OperationException;
use App\SubscriptionBilling\Models\CouponRedemption;
use App\SubscriptionBilling\Models\PendingLineItem;
use App\SubscriptionBilling\Models\Plan;
use App\SubscriptionBilling\Models\Subscription;
use App\SubscriptionBilling\Operations\ApproveSubscription;
use App\SubscriptionBilling\Operations\BillSubscription;
use App\SubscriptionBilling\Operations\CreateSubscription;
use App\SubscriptionBilling\Operations\EditSubscription;
use App\SubscriptionBilling\ValueObjects\SubscriptionStatus;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Exception;
use stdClass;

class SubscriptionTest extends AppTestCase
{
    private static Plan $plan2;
    private static Plan $quarterlyPlan;
    private static Plan $annualPlan;
    private static Coupon $coupon2;
    private static Subscription $subscriptionInArrears;
    private static Subscription $subscriptionInAdvance;
    private static Subscription $subscriptionWithAddons;
    private static Subscription $subscriptionWithContract;
    private static Subscription $subscriptionWithContractAuto;
    private static Subscription $subscriptionWithContractRenewOnce;
    private static Subscription $subscriptionWithShipTo;
    private static Subscription $subscriptionTaxInclusive;
    private static PaymentMethod $paymentMethod;
    private static array $proratedLineItemIds = [];
    private static Customer $autoCustomer;
    private static Subscription $trial;
    private static Subscription $snapped;
    private static Subscription $snapped2;
    private static ?Model $requester;
    private static int $currentTime;
    private static int $startOfToday;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        date_default_timezone_set('UTC');

        self::hasCompany();
        self::acceptsCreditCards(TestGateway::ID);
        self::hasCustomer();
        self::hasInactiveCustomer();
        self::hasCoupon();
        self::hasTaxRate();

        // enable manual contract renewals for testing
        self::$company->features->enable('subscription_manual_renewal');

        $customField1 = new CustomField();
        $customField1->id = 'collection_fails';
        $customField1->object = ObjectType::Invoice->typeName();
        $customField1->name = 'Failing Collections';
        $customField1->saveOrFail();

        self::$customer->payment_terms = 'NET 14';
        self::$customer->taxes = [self::$taxRate->id];
        self::$customer->saveOrFail();

        self::$plan = new Plan();
        self::$plan->create([
            'id' => 'test',
            'name' => 'Test',
            'amount' => 99,
            'interval' => Interval::MONTH,
            'interval_count' => 1,
            'description' => 'TD',
            'notes' => 'Notes',
        ]);

        self::$plan2 = new Plan();
        self::$plan2->create([
            'id' => 'test-2',
            'name' => 'Test 2',
            'amount' => 149,
            'interval' => Interval::MONTH,
            'interval_count' => 1,
        ]);

        self::$quarterlyPlan = new Plan();
        self::$quarterlyPlan->id = 'quarterly';
        self::$quarterlyPlan->name = 'Quarterly';
        self::$quarterlyPlan->amount = 500;
        self::$quarterlyPlan->interval = Interval::MONTH;
        self::$quarterlyPlan->interval_count = 2;
        self::$quarterlyPlan->saveOrFail();

        self::$annualPlan = new Plan();
        self::$annualPlan->id = 'annual';
        self::$annualPlan->name = 'Annual';
        self::$annualPlan->amount = 1490;
        self::$annualPlan->interval = Interval::YEAR;
        self::$annualPlan->interval_count = 1;
        self::$annualPlan->saveOrFail();

        self::$paymentMethod = PaymentMethod::instance(self::$company, PaymentMethod::CREDIT_CARD);
        self::$paymentMethod->enabled = true;
        self::$paymentMethod->saveOrFail();

        self::hasItem();

        self::$coupon2 = new Coupon();
        self::$coupon2->id = 'test-2';
        self::$coupon2->value = 3;
        self::$coupon2->name = 'Test 2';
        self::$coupon2->saveOrFail();

        // create an AutoPay customer
        self::$autoCustomer = new Customer();
        self::$autoCustomer->autopay = true;
        self::$autoCustomer->name = 'Test';
        self::$autoCustomer->saveOrFail();

        // set up customer payment source
        $card = self::getTestDataFactory()->createCard(self::$autoCustomer, MockGateway::ID, 'card_test', 0);
        self::$autoCustomer->setDefaultPaymentSource($card);
        self::hasBankAccount(MockGateway::ID);
        self::$bankAccount->verified = false;
        self::$bankAccount->saveOrFail();

        // enable subscription confirmation email
        $emailTemplate = new EmailTemplate();
        $emailTemplate->id = EmailTemplate::SUBSCRIPTION_CONFIRMATION;
        $emailTemplate->subject = 'subject';
        $emailTemplate->body = 'test';
        $options = $emailTemplate->options;
        $options[EmailTemplateOption::SEND_ON_SUBSCRIBE] = true;
        $emailTemplate->options = $options;
        $emailTemplate->saveOrFail();

        self::$requester = ACLModelRequester::get();

        self::$currentTime = time();
        self::$startOfToday = (int) mktime(
            0,
            0,
            0,
            (int) date('n', self::$currentTime),
            (int) date('j', self::$currentTime),
            (int) date('Y', self::$currentTime)
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        ACLModelRequester::set(self::$requester);
    }

    public function testUrl(): void
    {
        $subscription = new Subscription();
        $subscription->tenant_id = (int) self::$company->id();
        $subscription->client_id = 'blah';
        $this->assertEquals('http://'.self::$company->username.'.invoiced.localhost:1234/subscriptions/blah', $subscription->url);
    }

    public function testGetEmailVariables(): void
    {
        $subscription = new Subscription();
        $this->assertInstanceOf(SubscriptionEmailVariables::class, $subscription->getEmailVariables());
    }

    public function testEventAssociations(): void
    {
        $subscription = new Subscription();
        $subscription->customer = 100;
        $subscription->plan = 'test';

        $expected = [
            ['customer', 100],
        ];

        $this->assertEquals($expected, $subscription->getEventAssociations());
    }

    public function testEventObject(): void
    {
        $subscription = new Subscription();
        $subscription->setCustomer(self::$customer);
        $subscription->tenant_id = (int) self::$company->id();
        $subscription->setPlan(self::$plan);

        $expected = array_merge($subscription->toArray(), [
            'customer' => ModelNormalizer::toArray(self::$customer),
            'plan' => ModelNormalizer::toArray(self::$plan),
        ]);

        $this->assertEquals($expected, $subscription->getEventObject());
    }

    public function testBillingMode(): void
    {
        $subscription = new Subscription();
        $subscription->tenant_id = (int) self::$company->id();
        $billingMode = $subscription->billingMode();
        $this->assertInstanceOf(BillInAdvance::class, $billingMode);

        $subscription->bill_in_advance_days = 7;
        /** @var BillInAdvance $billingMode */
        $billingMode = $subscription->billingMode();
        $this->assertInstanceOf(BillInAdvance::class, $billingMode);
        $this->assertEquals(7, $billingMode->getBillInAdvanceDays());

        $subscription->bill_in = Subscription::BILL_IN_ARREARS;
        /** @var BillInArrears $billingMode */
        $billingMode = $subscription->billingMode();
        $this->assertInstanceOf(BillInArrears::class, $billingMode);
    }

    private function getCreateOperation(): CreateSubscription
    {
        return self::getService('test.create_subscription');
    }

    private function getEditOperation(): EditSubscription
    {
        return self::getService('test.edit_subscription');
    }

    private function getBillOperation(): BillSubscription
    {
        return self::getService('test.bill_subscription');
    }

    public function testCreateInvalidQuantity(): void
    {
        $this->expectException(OperationException::class);

        $this->getCreateOperation()->create([
            'customer' => self::$customer,
            'plan' => self::$plan,
            'quantity' => 0,
        ]);
    }

    public function testCreateInvalidCustomer(): void
    {
        $this->expectException(OperationException::class);

        $this->getCreateOperation()->create([
            'customer' => 12384234,
            'plan' => self::$plan,
        ]);
    }

    public function testCreateInvalidDiscounts(): void
    {
        $this->expectException(OperationException::class);

        $this->getCreateOperation()->create([
            'customer' => self::$customer,
            'plan' => self::$plan,
            'discounts' => ['this-coupon-does-not-exist'],
        ]);
    }

    public function testCreateInvalidTaxes(): void
    {
        $this->expectException(OperationException::class);

        $this->getCreateOperation()->create([
            'customer' => self::$customer,
            'plan' => self::$plan,
            'taxes' => [['not_a_tax']],
        ]);
    }

    public function testCreateInvalidPlan(): void
    {
        $this->expectException(OperationException::class);

        $this->getCreateOperation()->create([
            'customer' => self::$customer,
            'plan' => 'this-plan-does-not-exist',
        ]);
    }

    public function testCreateInvalidSnapDay(): void
    {
        $this->expectException(OperationException::class);

        $this->getCreateOperation()->create([
            'customer' => self::$customer,
            'plan' => self::$plan,
            'snap_to_nth_day' => 32,
        ]);
    }

    public function testCreateStartDateInPast(): void
    {
        $this->expectException(OperationException::class);

        $this->getCreateOperation()->create([
            'customer' => self::$customer,
            'plan' => self::$plan,
            'start_date' => strtotime('-6 years'),
        ]);
    }

    public function testCreateStartDateInPastAutoPay(): void
    {
        $this->expectException(OperationException::class);

        $this->getCreateOperation()->create([
            'customer' => self::$autoCustomer,
            'plan' => self::$plan,
            'start_date' => strtotime('-33 days'),
        ]);
    }

    public function testCreateWithInvalidPlanAddons(): void
    {
        $this->expectException(OperationException::class);

        $this->getCreateOperation()->create([
            'customer' => self::$customer,
            'plan' => self::$plan,
            'addons' => [['plan' => self::$annualPlan->id, 'quantity' => 2]],
        ]);
    }

    public function testCreateTooManyAddons(): void
    {
        $this->expectException(OperationException::class);

        $addons = [];
        for ($i = 0; $i < 101; ++$i) {
            $addons[] = ['plan' => self::$plan->id, 'quantity' => 2];
        }
        $this->getCreateOperation()->create([
            'customer' => self::$customer,
            'plan' => self::$plan,
            'addons' => $addons,
        ]);
    }

    public function testCreateInvalidBillInAdvance(): void
    {
        $this->expectException(OperationException::class);

        $this->getCreateOperation()->create([
            'customer' => self::$customer,
            'plan' => self::$plan,
            'quantity' => 2,
            'start_date' => self::$startOfToday,
            'bill_in' => Subscription::BILL_IN_ADVANCE,
            'bill_in_advance_days' => 32,
        ]);
    }

    public function testCreateInvalidSnapToNthDay(): void
    {
        $this->expectException(OperationException::class);

        $this->getCreateOperation()->create([
            'customer' => self::$customer,
            'plan' => self::$quarterlyPlan,
            'snap_to_nth_day' => 2,
        ]);
    }

    public function testCreate(): void
    {
        EventSpool::enable();

        $createSubscription = self::getService('test.create_subscription');
        self::$subscription = $createSubscription->create([
            'customer' => self::$customer,
            'plan' => self::$plan,
            'quantity' => 2,
        ]);

        $this->assertEquals(self::$company->id(), self::$subscription->tenant_id);
        $this->assertEquals(48, strlen(self::$subscription->client_id));

        // should generate first invoice
        $this->assertEquals(1, Invoice::count());
        $this->assertGreaterThan(self::$subscription->start_date, self::$subscription->renews_next);
    }

    public function testCreateBillInArrears(): void
    {
        EventSpool::enable();

        self::$subscriptionInArrears = $this->getCreateOperation()->create([
            'customer' => self::$customer,
            'plan' => self::$plan,
            'quantity' => 2,
            'start_date' => self::$startOfToday,
            'bill_in' => Subscription::BILL_IN_ARREARS,
        ]);

        $this->assertEquals(self::$company->id(), self::$subscriptionInArrears->tenant_id);
        $this->assertEquals(48, strlen(self::$subscriptionInArrears->client_id));

        // should generate first invoice
        $this->assertEquals(1, Invoice::count());
        $this->assertGreaterThan(self::$subscriptionInArrears->start_date, (int) self::$subscriptionInArrears->renews_next);
        $this->assertTimestampsEqual(self::$startOfToday, (int) self::$subscriptionInArrears->period_start);
        $interval = new Interval(1, Interval::MONTH);
        $this->assertTimestampsEqual((int) $interval->addTo(self::$startOfToday) - 1, (int) self::$subscriptionInArrears->period_end);
    }

    public function testCreateWithAddons(): void
    {
        self::$subscriptionWithAddons = $this->getCreateOperation()->create([
            'customer' => self::$customer,
            'plan' => self::$plan,
            'discounts' => ['coupon'],
            'taxes' => ['tax'],
            'addons' => [
                [
                    'catalog_item' => self::$item->id,
                    'quantity' => 2,
                ],
                [
                    'plan' => self::$plan2->id,
                ],
            ],
        ]);

        $this->assertEquals(48, strlen(self::$subscriptionWithAddons->client_id));
        $this->assertNotEquals(self::$subscription->client_id, self::$subscriptionWithAddons->client_id);

        // should create a coupon redemption
        $redemption = CouponRedemption::where('parent_type', 'subscription')
            ->where('parent_id', self::$subscriptionWithAddons)
            ->where('coupon', self::$coupon->id)
            ->oneOrNull();
        $this->assertInstanceOf(CouponRedemption::class, $redemption);
    }

    public function testCreateWithContractTerms(): void
    {
        self::$subscriptionWithContract = $this->getCreateOperation()->create([
            'customer' => self::$customer,
            'plan' => self::$plan,
            'cycles' => 4,
            'contract_renewal_mode' => Subscription::RENEWAL_MODE_MANUAL,
            'contract_renewal_cycles' => 5,
            'addons' => [
                [
                    'catalog_item' => self::$item->id,
                    'quantity' => 2,
                ],
                [
                    'plan' => self::$plan2->id,
                ],
            ],
        ]);

        $this->assertEquals(48, strlen(self::$subscriptionWithContract->client_id));
        $this->assertNotEquals(self::$subscription->client_id, self::$subscriptionWithContract->client_id);
        $this->assertTimestampsEqual(self::$subscriptionWithContract->start_date, (int) self::$subscriptionWithContract->contract_period_start);
        $end = self::$subscriptionWithContract->start_date;
        $interval = new Interval(1, Interval::MONTH);
        for ($i = 0; $i < 4; ++$i) {
            $end = $interval->addTo((int) $end);
        }
        $this->assertTimestampsEqual($end - 1, (int) self::$subscriptionWithContract->contract_period_end);
    }

    public function testCreateWithContractTermsAutoRenewal(): void
    {
        self::$subscriptionWithContractAuto = $this->getCreateOperation()->create([
            'customer' => self::$customer,
            'plan' => self::$plan,
            'contract_renewal_mode' => Subscription::RENEWAL_MODE_AUTO,
            'cycles' => 1,
        ]);

        $this->assertTimestampsEqual(self::$subscriptionWithContractAuto->start_date, (int) self::$subscriptionWithContractAuto->contract_period_start);
        $interval = new Interval(1, Interval::MONTH);
        $this->assertTimestampsEqual(
            $interval->addTo(self::$subscriptionWithContractAuto->start_date) - 1,
            (int) self::$subscriptionWithContractAuto->contract_period_end
        );
    }

    public function testCreateWithContractTermsRenewOnceRenewal(): void
    {
        self::$subscriptionWithContractRenewOnce = $this->getCreateOperation()->create([
            'customer' => self::$customer,
            'plan' => self::$plan,
            'contract_renewal_mode' => Subscription::RENEWAL_MODE_RENEW_ONCE,
            'cycles' => 1,
        ]);

        $this->assertTimestampsEqual(self::$subscriptionWithContractRenewOnce->start_date, (int) self::$subscriptionWithContractRenewOnce->contract_period_start);
        $interval = new Interval(1, Interval::MONTH);
        $this->assertTimestampsEqual(
            $interval->addTo(self::$subscriptionWithContractAuto->start_date) - 1,
            (int) self::$subscriptionWithContractRenewOnce->contract_period_end
        );
    }

    public function testCreateTrial(): void
    {
        $t = strtotime('+1 month', self::$startOfToday);

        self::$trial = $this->getCreateOperation()->create([
            'customer' => self::$customer,
            'plan' => self::$plan,
            'start_date' => $t,
        ]);

        --$t;

        $this->assertEquals(SubscriptionStatus::TRIALING, self::$trial->status);
        $this->assertTimestampsEqual(self::$startOfToday, (int) self::$trial->period_start);
        $this->assertTimestampsEqual($t, (int) self::$trial->period_end);
    }

    public function testCreateAutoPay(): void
    {
        // create a subscription that starts now
        $subscription = $this->getCreateOperation()->create([
            'customer' => self::$autoCustomer,
            'plan' => self::$plan,
        ]);

        // should create an invoice
        $invoice = Invoice::where('subscription_id', $subscription->id())->oneOrNull();
        $this->assertInstanceOf(Invoice::class, $invoice);

        // should have successfully collected on that invoice
        $this->assertTrue($invoice->paid);
    }

    public function testCreateAutoPayWithPaymentDelay(): void
    {
        // create a subscription that starts now
        // but charged in 10 days
        $customer = self::$autoCustomer;
        $customer->autopay_delay_days = 10;
        $customer->saveOrFail();
        $subscription = $this->getCreateOperation()->create([
            'customer' => $customer,
            'plan' => self::$plan,
        ]);

        // should create an invoice
        $invoice = Invoice::where('subscription_id', $subscription->id())->oneOrNull();
        $this->assertInstanceOf(Invoice::class, $invoice);

        // should have successfully collected on that invoice
        $this->assertFalse($invoice->paid);
        // substitute 10 days
        $delta = CarbonImmutable::createFromTimestamp((int) $invoice->next_payment_attempt)->diffInHours(CarbonImmutable::createFromTimestamp($invoice->date)->addDays(10));
        $this->assertEquals(0, $delta, 'Invoice next payment attempt should be 10 days ahead');

        // restore the customer
        $customer->autopay_delay_days = -1;
        $customer->saveOrFail();
    }

    public function testCreateAutoPayWithCredits(): void
    {
        // add credits to customer account
        $credit = new Transaction();
        $credit->type = Transaction::TYPE_ADJUSTMENT;
        $credit->setCustomer(self::$autoCustomer);
        $credit->amount = -self::$plan->amount;
        $this->assertTrue($credit->save());

        // create a subscription that starts now
        $subscription = $this->getCreateOperation()->create([
            'customer' => self::$autoCustomer,
            'plan' => self::$plan,
        ]);

        // should create an invoice
        $invoice = Invoice::where('subscription_id', $subscription->id())->oneOrNull();
        $this->assertInstanceOf(Invoice::class, $invoice);

        // should have successfully collected on that invoice
        $this->assertTrue($invoice->paid);
    }

    public function testCreateAutoPayFail(): void
    {
        self::getService('test.event_spool')->flush();
        EventSpool::enable();

        $customer = new Customer();
        $customer->autopay = true;
        $customer->name = 'Testing';
        $customer->saveOrFail();
        $card = self::getTestDataFactory()->createCard($customer, MockGateway::ID, 'card_test', 0);
        $customer->setDefaultPaymentSource($card);

        // create a pending line item that is billed also
        $lineItem = new PendingLineItem();
        $lineItem->setParent($customer);
        $lineItem->name = 'test';
        $lineItem->unit_cost = 100;
        $lineItem->saveOrFail();

        // determine the next number in the sequence (for comparison later)
        $invoice = new Invoice();
        $invoice->tenant_id = (int) self::getService('test.tenant')->get()->id();
        $numberingSequence = $invoice->getNumberingSequence();
        $pastNext = $numberingSequence->nextNumber();

        // Create a subscription that starts now
        // should fail because collecting on the generated
        // invoice throws an exception.
        // Attempted a few times to test that the
        // auto numbering sequence is rolled back.
        for ($i = 0; $i < 5; ++$i) {
            try {
                $this->getCreateOperation()->create([
                    'customer' => $customer,
                    'plan' => self::$plan,
                    'metadata' => (object) ['collection_fails' => true], // causes the test gateway to simulate a failed collection attempt
                ]);
                throw new Exception('Test case has failed');
            } catch (OperationException $e) {
                $this->assertEquals('Payment declined.', $e->getMessage());
            }
        }

        // should roll back the invoices
        $this->assertEquals(0, Invoice::where('customer', $customer)->count());

        // should not create any events
        $this->assertEquals(0, self::getService('test.event_spool')->size());

        // should not touch the line item
        $lineItem->refresh();
        $this->assertEquals($customer->id(), $lineItem->customer_id);

        // should roll back the invoice numbering sequence
        $this->assertEquals($pastNext, $numberingSequence->nextNumber());
    }

    public function testCreateAutoPayNoPaymentSource(): void
    {
        // create a subscription that starts now
        $this->assertTrue(self::$autoCustomer->clearDefaultPaymentSource());
        $subscription = $this->getCreateOperation()->create([
            'customer' => self::$autoCustomer,
            'plan' => self::$plan,
        ]);

        // should create an invoice
        $invoice = Invoice::where('subscription_id', $subscription->id())->oneOrNull();
        $this->assertInstanceOf(Invoice::class, $invoice);

        // should not collect on the invoice
        $this->assertFalse($invoice->paid);
    }

    public function testCreateAutoPayNoVerifiedPaymentSource(): void
    {
        // create a subscription that starts now
        $subscription = $this->getCreateOperation()->create([
            'customer' => self::$autoCustomer,
            'plan' => self::$plan,
        ]);
        $this->assertTrue(self::$autoCustomer->setDefaultPaymentSource(self::$bankAccount));

        // should create an invoice
        $invoice = Invoice::where('subscription_id', $subscription->id())->oneOrNull();
        $this->assertInstanceOf(Invoice::class, $invoice);

        // should not collect on the invoice
        $this->assertFalse($invoice->paid);
    }

    public function testCreateCalendarBilling(): void
    {
        $start = self::$startOfToday;

        $n = (1 == date('j')) ? 2 : 1; // use the 1st, unless its already the 1st
        self::$snapped = $this->getCreateOperation()->create([
            'customer' => self::$customer,
            'plan' => self::$plan,
            'start_date' => $start,
            'snap_to_nth_day' => $n,
        ]);

        // should have a billing period snapped to the nth day
        // of the next month
        $snapped = (int) mktime(0, 0, 0, (int) date('n'), $n, (int) date('Y'));
        if (1 == $n) {
            $snapped = (int) strtotime('+1 month', $snapped);
        }

        $this->assertTimestampsEqual($start, self::$snapped->start_date);
        $this->assertTimestampsEqual($start, (int) self::$snapped->renewed_last);
        $this->assertTimestampsEqual($snapped, (int) self::$snapped->renews_next);
        $this->assertTimestampsEqual($start, (int) self::$snapped->period_start);
        $this->assertTimestampsEqual($snapped - 1, (int) self::$snapped->period_end);
        $this->assertEquals($n, self::$snapped->snap_to_nth_day);

        // should create a prorated invoice
        $invoice = Invoice::where('subscription_id', self::$snapped->id())->oneOrNull();
        $this->assertInstanceOf(Invoice::class, $invoice);
        $items = $invoice->items();
        $this->assertCount(1, $items);
        $this->assertTrue($items[0]['prorated']);
        // should be prorated with subtotal somewhere between 0 and 99
        $this->assertGreaterThan(0, $invoice->subtotal);
        $this->assertLessThan(99, $invoice->subtotal);
    }

    public function testCreateCalendarBillingWithTrial(): void
    {
        $nextYear = (int) date('Y') + 1;
        self::$snapped2 = $this->getCreateOperation()->create([
            'customer' => self::$customer,
            'plan' => self::$plan,
            'start_date' => mktime(0, 0, 0, 1, 15, $nextYear),
            'snap_to_nth_day' => 2,
        ]);

        $this->assertTimestampsEqual((int) mktime(0, 0, 0, 1, 15, $nextYear), self::$snapped2->start_date);
        $this->assertNull(self::$snapped2->renewed_last);
        $this->assertTimestampsEqual((int) mktime(0, 0, 0, 1, 15, $nextYear), (int) self::$snapped2->renews_next);
        // allow range for slow tests
        $this->assertLessThan(3, abs(self::$startOfToday - self::$snapped2->period_start));
        $this->assertTimestampsEqual((int) mktime(0, 0, 0, 1, 15, $nextYear) - 1, (int) self::$snapped2->period_end);
        $this->assertEquals(2, self::$snapped2->snap_to_nth_day);
    }

    public function testCreateCalendarBillingNoProration(): void
    {
        $start = self::$startOfToday;
        $n = (1 == date('j')) ? 2 : 1; // use the 1st, unless its already the 1st

        $subscription = $this->getCreateOperation()->create([
            'customer' => self::$customer,
            'plan' => self::$plan,
            'prorate' => false,
            'start_date' => $start,
            'snap_to_nth_day' => $n,
        ]);

        // should have a billing period snapped to the nth day
        // of the next month
        $snapped = (int) mktime(0, 0, 0, (int) date('n'), $n, (int) date('Y'));
        if (1 == $n) {
            $snapped = (int) strtotime('+1 month', $snapped);
        }

        $this->assertTimestampsEqual($start, $subscription->start_date);
        $this->assertTimestampsEqual($start, (int) $subscription->renewed_last);
        $this->assertTimestampsEqual($snapped, (int) $subscription->renews_next);
        $this->assertTimestampsEqual($start, (int) $subscription->period_start);
        $this->assertTimestampsEqual($snapped - 1, (int) $subscription->period_end);
        $this->assertEquals($n, $subscription->snap_to_nth_day);

        // should have create a non-prorated invoice
        $invoice = Invoice::where('subscription_id', $subscription->id())->oneOrNull();
        $this->assertInstanceOf(Invoice::class, $invoice);
        $items = $invoice->items();
        $this->assertCount(1, $items);
        $this->assertFalse($items[0]['prorated']);
        $this->assertEquals(99, $invoice->subtotal);
    }

    public function testCreateZeroPlan(): void
    {
        $plan = new Plan();
        $plan->name = 'Zero';
        $plan->id = 'zero';
        $plan->amount = 0;
        $plan->interval = Interval::MONTH;
        $plan->interval_count = 1;
        $plan->saveOrFail();

        $subscription = $this->getCreateOperation()->create([
            'customer' => self::$customer,
            'plan' => $plan,
        ]);
        $this->assertTrue($subscription->persisted());
    }

    public function testCreateBillInAdvance(): void
    {
        EventSpool::enable();

        self::$subscriptionInAdvance = $this->getCreateOperation()->create([
            'customer' => self::$customer,
            'plan' => self::$plan,
            'interval_count' => 2,
            'quantity' => 2,
            'start_date' => self::$startOfToday,
            'bill_in' => Subscription::BILL_IN_ADVANCE,
            'bill_in_advance_days' => 7,
        ]);

        $this->assertEquals(self::$company->id(), self::$subscriptionInAdvance->tenant_id);
        $this->assertEquals(48, strlen(self::$subscriptionInAdvance->client_id));

        $this->assertGreaterThan(self::$subscriptionInAdvance->start_date, (int) self::$subscriptionInAdvance->renews_next);
        $this->assertTimestampsEqual(self::$startOfToday, (int) self::$subscriptionInAdvance->period_start);
        $interval = self::$subscriptionInAdvance->plan()->interval();
        $this->assertTimestampsEqual($interval->addTo(self::$startOfToday) - 1, (int) self::$subscriptionInAdvance->period_end);
        $this->assertTimestampsEqual((int) strtotime('-7 days', self::$subscriptionInAdvance->period_end + 1), (int) self::$subscriptionInAdvance->renews_next);
    }

    public function testCreateShipTo(): void
    {
        self::$subscriptionWithShipTo = $this->getCreateOperation()->create([
            'customer' => self::$customer,
            'plan' => self::$plan,
            'ship_to' => [
                'name' => 'Test',
                'address1' => '1234 main st',
                'city' => 'Austin',
                'state' => 'TX',
                'postal_code' => '78735',
                'country' => 'US',
            ],
        ]);

        /** @var ShippingDetail $shipping2 */
        $shipping2 = self::$subscriptionWithShipTo->ship_to;
        $this->assertInstanceOf(ShippingDetail::class, $shipping2);
        $expected = [
            'address1' => '1234 main st',
            'address2' => null,
            'attention_to' => null,
            'city' => 'Austin',
            'country' => 'US',
            'name' => 'Test',
            'postal_code' => '78735',
            'state' => 'TX',
        ];
        $shipTo = $shipping2->toArray();
        unset($shipTo['created_at']);
        unset($shipTo['updated_at']);
        $this->assertEquals($expected, $shipTo);
    }

    public function testCreateTaxInclusive(): void
    {
        $customer = new Customer();
        $customer->name = 'Inclusive';
        $customer->saveOrFail();

        $plan = new Plan();
        $plan->id = 'inclusive';
        $plan->name = 'Inclusive';
        $plan->amount = 100;
        $plan->interval = Interval::MONTH;
        $plan->interval_count = 1;
        $plan->saveOrFail();

        $taxRate = new TaxRate();
        $taxRate->id = 'inclusive';
        $taxRate->name = 'Inclusive';
        $taxRate->inclusive = true;
        $taxRate->value = 5;
        $taxRate->saveOrFail();

        self::$subscriptionTaxInclusive = $this->getCreateOperation()->create([
            'customer' => $customer,
            'plan' => $plan,
            'taxes' => ['inclusive'],
        ]);
        $this->assertTrue(self::$subscriptionTaxInclusive->persisted());
    }

    /**
     * @depends testCreate
     */
    public function testEventCreated(): void
    {
        $this->assertHasEvent(self::$subscription, EventType::SubscriptionCreated);
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'id' => self::$subscription->id(),
            'object' => 'subscription',
            'customer' => self::$customer->id(),
            'plan' => self::$plan->id,
            'quantity' => 2,
            'description' => null,
            'cycles' => null,
            'start_date' => self::$subscription->start_date,
            'renewed_last' => self::$subscription->start_date,
            'period_start' => self::$subscription->start_date,
            'renews_next' => self::$subscription->renews_next,
            'period_end' => self::$subscription->renews_next - 1,
            'contract_renewal_mode' => Subscription::RENEWAL_MODE_NONE,
            'contract_renewal_cycles' => null,
            'contract_period_start' => null,
            'contract_period_end' => null,
            'cancel_at_period_end' => false,
            'canceled_at' => null,
            'canceled_reason' => null,
            'addons' => [],
            'discounts' => [],
            'taxes' => [],
            'mrr' => 198.0,
            'recurring_total' => 207.90,
            'payment_source' => null,
            'status' => SubscriptionStatus::ACTIVE,
            'url' => 'http://'.self::$company->username.'.invoiced.localhost:1234/subscriptions/'.self::$subscription->client_id,
            'approval' => null,
            'metadata' => new stdClass(),
            'created_at' => self::$subscription->created_at,
            'updated_at' => self::$subscription->updated_at,
            'paused' => false,
            'bill_in' => 'advance',
            'bill_in_advance_days' => 0,
            'ship_to' => null,
            'snap_to_nth_day' => null,
            'amount' => null,
        ];

        $this->assertEquals($expected, self::$subscription->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testToSearchDocument(): void
    {
        $expected = [
            'plan' => [
                'id' => 'test',
                'name' => 'Test',
                'interval' => 'month',
                'interval_count' => 1,
                'currency' => 'usd',
            ],
            'recurring_total' => 207.90,
            'status' => SubscriptionStatus::ACTIVE,
            'metadata' => [],
            '_customer' => self::$customer->id(),
            'customer' => [
                'name' => self::$customer->name,
            ],
        ];

        $this->assertEquals($expected, (new SearchDocumentFactory())->make(self::$subscription));
    }

    /**
     * @depends testCreate
     * @depends testCreateWithAddons
     */
    public function testToArrayHook(): void
    {
        $expected = [
            'addons' => self::$subscriptionWithAddons->addons(true),
            'discounts' => [self::$coupon->toArray()],
            'taxes' => [self::$taxRate->toArray()],
            'customerName' => self::$customer->name,
        ];

        $result = [
            'discounts' => self::$subscriptionWithAddons->discounts,
            'taxes' => self::$subscriptionWithAddons->taxes,
        ];
        self::$subscriptionWithAddons->toArrayHook($result, [], ['customerName' => true], ['addons' => ['catalog_item' => true]]);

        $this->assertEquals($expected, $result);
    }

    /**
     * @depends testCreate
     *
     * @doesNotPerformAssertions
     */
    public function testEdit(): void
    {
        EventSpool::enable();

        $this->getEditOperation()->modify(self::$subscription, [
            'description' => 'Test Edit',
        ]);
    }

    public function testEditCancelAtPeriodEnd(): void
    {
        $this->getEditOperation()->modify(self::$subscription, [
            'cancel_at_period_end' => true,
        ]);

        // should set the `canceled_at` timestamp
        $this->assertLessThan(3, abs(self::$subscription->canceled_at - time()), '`canceled_at` was not set to the current timestamp');
    }

    /**
     * @depends testEdit
     */
    public function testEventEdited(): void
    {
        $this->assertHasEvent(self::$subscription, EventType::SubscriptionUpdated);
    }

    /**
     * @depends testCreate
     */
    public function testCannotEditStartDate(): void
    {
        $this->expectException(OperationException::class);
        $this->getEditOperation()->modify(self::$subscription, [
            'start_date' => time(),
        ]);
    }

    public function testCannotEditCustomer(): void
    {
        $this->expectException(OperationException::class);
        $this->expectExceptionMessage('Invalid request parameter `customer`. The customer cannot be modified.');
        $this->getEditOperation()->modify(self::$subscription, [
            'customer' => -1,
        ]);
    }

    /**
     * @depends testCreateShipTo
     */
    public function testEditShipTo(): void
    {
        // change the address
        $this->getEditOperation()->modify(self::$subscriptionWithShipTo, [
            'ship_to' => [
                'name' => 'Test',
                'address1' => '5301 southwest parkway',
                'city' => 'Austin',
                'state' => 'TX',
                'postal_code' => '78735',
                'country' => 'US',
            ],
        ]);

        /** @var ShippingDetail $shipping2 */
        $shipping2 = self::$subscriptionWithShipTo->ship_to;
        $this->assertInstanceOf(ShippingDetail::class, $shipping2);
        $expected = [
            'address1' => '5301 southwest parkway',
            'address2' => null,
            'attention_to' => null,
            'city' => 'Austin',
            'country' => 'US',
            'name' => 'Test',
            'postal_code' => '78735',
            'state' => 'TX',
        ];
        $shipTo = $shipping2->toArray();
        unset($shipTo['created_at']);
        unset($shipTo['updated_at']);
        $this->assertEquals($expected, $shipTo);

        // remove the ship to altogether
        $this->getEditOperation()->modify(self::$subscriptionWithShipTo, [
            'ship_to' => null,
        ]);

        $this->assertNull(self::$subscriptionWithShipTo->ship_to);
    }

    /**
     * @depends testCreateTrial
     */
    public function testExtendTrial(): void
    {
        $end = strtotime('+2 months', self::$startOfToday);
        $this->getEditOperation()->modify(self::$trial, ['period_end' => $end]);

        // should change the current period and the start date
        // (end of the trial)
        $this->assertTimestampsEqual(self::$startOfToday, (int) self::$trial->period_start);
        $this->assertTimestampsEqual($end - 1, (int) self::$trial->period_end);
        $this->assertTimestampsEqual($end, self::$trial->start_date);
    }

    /**
     * @depends testCreateWithAddons
     */
    public function testEditCoupons(): void
    {
        // add a coupon
        $this->getEditOperation()->modify(self::$subscriptionWithAddons, [
            'discounts' => [self::$coupon->id, self::$coupon2->id],
        ]);

        $redemptions = self::$subscriptionWithAddons->couponRedemptions();
        $this->assertCount(2, $redemptions);

        // and remove it
        $this->getEditOperation()->modify(self::$subscriptionWithAddons, [
            'discounts' => [self::$coupon->id],
        ]);

        $redemptions = self::$subscriptionWithAddons->couponRedemptions();
        $this->assertCount(1, $redemptions);
    }

    /**
     * @depends testCreate
     *
     * @doesNotPerformAssertions
     */
    public function testEmail(): void
    {
        self::$subscription->clearCache();
        $emailTemplate = EmailTemplate::make(self::$subscription->tenant_id, EmailTemplate::SUBSCRIPTION_BILLED_SOON);
        self::getService('test.email_spool')->spoolDocument(self::$subscription, $emailTemplate, [])->flush();
    }

    /**
     * @depends testCreate
     */
    public function testStatusActive(): void
    {
        $this->assertEquals(SubscriptionStatus::ACTIVE, self::$subscription->status);
    }

    /**
     * @depends testCreate
     */
    public function testStatusPastDue(): void
    {
        $inv = new Invoice();
        $inv->setCustomer(self::$customer);
        $inv->subscription_id = (int) self::$subscription->id();
        $inv->due_date = time() - 3600;
        $inv->items = [
            [
                'quantity' => 1,
                'unit_cost' => 10,
            ],
        ];
        $inv->saveOrFail();
        $this->assertEquals(InvoiceStatus::PastDue->value, $inv->status);

        // creating a past due invoice should trigger
        // an update on the subscription status
        $this->assertEquals(SubscriptionStatus::PAST_DUE, self::$subscription->refresh()->status);

        // paying invoice should change subscription back to active
        $txn = new Transaction();
        $txn->setCustomer(self::$customer);
        $txn->setInvoice($inv);
        $txn->amount = $inv->balance;
        $this->assertTrue($txn->save());

        $this->assertEquals(SubscriptionStatus::ACTIVE, self::$subscription->refresh()->status);
    }

    /**
     * @depends testCreate
     * @depends testCreateWithAddons
     */
    public function testAddons(): void
    {
        $this->assertEquals([], self::$subscription->addons());

        $expected = [
            [
                'quantity' => 2,
                'catalog_item' => self::$item->id,
                'plan' => null,
                'description' => null,
                'amount' => null,
            ],
            [
                'quantity' => 1,
                'catalog_item' => null,
                'plan' => self::$plan2->id,
                'description' => null,
                'amount' => null,
            ],
        ];
        $addons = self::$subscriptionWithAddons->addons();

        foreach ($addons as &$addon) {
            unset($addon['id']);
            unset($addon['created_at']);
            unset($addon['updated_at']);
            unset($addon['object']);
        }

        $this->assertEquals($expected, $addons);
    }

    /**
     * @depends testCreate
     * @depends testCreateWithAddons
     * @depends testCreateTaxInclusive
     */
    public function testRecurringTotal(): void
    {
        $this->assertEquals(207.9, self::$subscription->recurring_total);
        $this->assertEquals(2242.38, self::$subscriptionWithAddons->recurring_total);
        $this->assertEquals(100.0, self::$subscriptionTaxInclusive->recurring_total);
    }

    /**
     * @depends testCreate
     * @depends testCreateWithAddons
     * @depends testCreateTaxInclusive
     */
    public function testMrr(): void
    {
        $this->assertEquals(198, self::$subscription->mrr);
        $this->assertEquals(2135.6, self::$subscriptionWithAddons->mrr);
        $this->assertEquals(95.23, self::$subscriptionTaxInclusive->mrr);
    }

    /**
     * @depends testCreate
     */
    public function testQuery(): void
    {
        $subscriptions = Subscription::all();

        $this->assertCount(19, $subscriptions);

        $find = [
            self::$subscription->id(),
            self::$subscriptionWithAddons->id(),
            self::$subscriptionWithContract->id(),
            self::$subscriptionWithContractAuto->id(),
            self::$subscriptionWithContractRenewOnce->id(),
            self::$trial->id(),
            self::$snapped->id(),
            self::$snapped2->id(),
        ];
        foreach ($subscriptions as $subscription) {
            if (false !== ($key = array_search($subscription->id(), $find))) {
                unset($find[$key]);
            }
        }
        $this->assertCount(0, $find);
    }

    /**
     * @depends testCreate
     */
    public function testQueryCustomFieldRestriction(): void
    {
        $member = new Member();
        $member->role = Role::ADMINISTRATOR;
        $member->setUser(self::getService('test.user_context')->get());
        $member->restriction_mode = Member::CUSTOM_FIELD_RESTRICTION;
        $member->restrictions = ['territory' => ['Texas']];

        ACLModelRequester::set($member);

        $this->assertEquals(0, Subscription::count());

        // update the customer territory
        self::$customer->metadata = (object) ['territory' => 'Texas'];
        self::$customer->saveOrFail();

        $subscriptions = Subscription::all();
        $this->assertCount(13, $subscriptions);
        $this->assertEquals(self::$subscription->id(), $subscriptions[0]->id());
        $this->assertEquals(self::$subscriptionInArrears->id(), $subscriptions[1]->id());
        $this->assertEquals(self::$subscriptionWithAddons->id(), $subscriptions[2]->id());
        $this->assertEquals(self::$subscriptionWithContract->id(), $subscriptions[3]->id());
        $this->assertEquals(self::$subscriptionWithContractAuto->id(), $subscriptions[4]->id());
        $this->assertEquals(self::$subscriptionWithContractRenewOnce->id(), $subscriptions[5]->id());
        $this->assertEquals(self::$trial->id(), $subscriptions[6]->id());
        $this->assertEquals(self::$snapped->id(), $subscriptions[7]->id());
        $this->assertEquals(self::$snapped2->id(), $subscriptions[8]->id());
    }

    /**
     * @depends testCreate
     */
    public function testFindClientId(): void
    {
        $this->assertNull(Subscription::findClientId(''));
        $this->assertNull(Subscription::findClientId('1234'));

        $this->assertEquals(self::$subscription->id(), Subscription::findClientId(self::$subscription->client_id)->id()); /* @phpstan-ignore-line */

        $old = self::$subscription->client_id;
        self::$subscription->refreshClientId();
        $this->assertNotEquals($old, self::$subscription->client_id);

        // set client ID in the past
        self::$subscription->refreshClientId(false, strtotime('-1 year'));
        /** @var Subscription $obj */
        $obj = Subscription::findClientId(self::$subscription->client_id);

        // set the client ID to expire soon
        self::$subscription->refreshClientId(false, (int) strtotime('+29 days'));
        /** @var Subscription $obj */
        $obj = Subscription::findClientId(self::$subscription->client_id);
    }

    /**
     * @depends testCreate
     */
    public function testCanceledNotHidden(): void
    {
        self::getService('test.cancel_subscription')->cancel(self::$snapped);

        $subscriptions = Subscription::all();
        $this->assertCount(19, $subscriptions);

        $subscriptions = Subscription::where('canceled', false)->all();
        $this->assertCount(18, $subscriptions);

        $subscriptions = Subscription::where('canceled', true)->all();
        $this->assertCount(1, $subscriptions);
    }

    /**
     * @depends testCreate
     */
    public function testUpgrade(): void
    {
        // set to renew in the future
        $this->getEditOperation()->modify(self::$subscription->clearCache(), [
            'renews_next' => strtotime('+1 month'),
        ]);

        $this->getEditOperation()->modify(self::$subscription, [
            'plan' => self::$plan2->id,
        ]);

        $this->assertEquals(self::$plan2->internal_id, self::$subscription->plan_id);

        // should create proration pending line items
        $lines = PendingLineItem::where('customer_id', self::$customer->id())
            ->sort('id DESC')
            ->first(2);

        $this->assertCount(2, $lines);
        $this->assertStringContainsString('(removed 2)', (string) $lines[1]->description);
        $this->assertStringContainsString('(added 2)', (string) $lines[0]->description);
        $this->assertTrue($lines[0]->prorated);
        $this->assertTrue($lines[1]->prorated);
        $this->assertLessThan(0, $lines[1]->amount);
        $this->assertGreaterThan(0, $lines[0]->amount);

        foreach ($lines as $line) {
            $this->assertFalse(in_array($line->id(), self::$proratedLineItemIds));
            self::$proratedLineItemIds[] = $line->id();
        }
    }

    /**
     * @depends testUpgrade
     */
    public function testNoProration(): void
    {
        // set to renew in the future
        $this->getEditOperation()->modify(self::$subscription->clearCache(), [
            'renews_next' => strtotime('+1 month'),
        ]);

        // downgrade
        $this->getEditOperation()->modify(self::$subscription, [
            'plan' => self::$plan->id,
            'prorate' => false,
        ]);

        // upgrade
        $this->getEditOperation()->modify(self::$subscription, [
            'plan' => self::$plan2->id,
            'prorate' => false,
        ]);

        // should not create any new pending line items
        $lines = PendingLineItem::where('customer_id', self::$customer->id())
            ->all();

        $this->assertCount(2, $lines);
    }

    /**
     * @depends testUpgrade
     */
    public function testDowngrade(): void
    {
        // set to renew in the future
        $this->getEditOperation()->modify(self::$subscription->clearCache(), [
            'renews_next' => strtotime('+1 month'),
        ]);

        self::$subscription->setPlan(self::$plan);
        self::$subscription->prorate = true;
        $this->getEditOperation()->modify(self::$subscription, [
            'plan' => self::$plan->id,
            'prorate' => true,
        ]);

        // should create pending line items
        $lines = PendingLineItem::where('customer_id', self::$customer->id())
            ->sort('id DESC')
            ->first(2);

        $this->assertCount(2, $lines);
        $this->assertStringContainsString('(removed 2)', (string) $lines[1]->description);
        $this->assertStringContainsString('(added 2)', (string) $lines[0]->description);
        $this->assertTrue($lines[0]->prorated);
        $this->assertTrue($lines[1]->prorated);
        $this->assertLessThan(0, $lines[1]->amount);
        $this->assertGreaterThan(0, $lines[0]->amount);

        foreach ($lines as $line) {
            $this->assertFalse(in_array($line->id(), self::$proratedLineItemIds));
            $proratedLineItemIds[] = $line->id();
        }
    }

    /**
     * @depends testCreate
     */
    public function testUpgradeWithProrationDate(): void
    {
        $interval = self::$subscription->plan()->interval();
        $frequencyTimeString = $interval->duration();
        $end = (int) self::$subscription->period_end;
        $start = strtotime('-'.$frequencyTimeString, $end) + 1;

        $this->getEditOperation()->modify(self::$subscription, [
            'quantity' => self::$subscription->quantity + 1,
            'proration_date' => (int) floor($start + ($end - $start) / 2),
        ]);

        // should create proration pending line items
        $line = PendingLineItem::where('customer_id', self::$customer->id())
            ->sort('id DESC')
            ->one();

        $this->assertStringContainsString('(added 1)', (string) $line->description);
        // this value will differ depending on the count of days in the month, for 28 days it will be up to 0.55xxx which will be rounded to 0.6

        $this->assertBetween((int) ($line->quantity * 10), 4, 6);
        $this->assertTrue($line->prorated);
        $this->assertGreaterThan(0, $line->amount);

        $this->assertFalse(in_array($line->id(), self::$proratedLineItemIds));
        self::$proratedLineItemIds[] = $line->id();
    }

    /**
     * @depends testCreate
     */
    public function testChangeQuantity(): void
    {
        // set to renew in the future
        $this->getEditOperation()->modify(self::$subscription->clearCache(), [
            'renews_next' => strtotime('+1 month'),
        ]);

        $this->getEditOperation()->modify(self::$subscription, [
            'quantity' => self::$subscription->quantity + 1,
        ]);

        // should create pending line items
        $line = PendingLineItem::where('customer_id', self::$customer->id())
            ->sort('id DESC')
            ->oneOrNull();

        $this->assertInstanceOf(PendingLineItem::class, $line);
        $this->assertStringContainsString('(added 1)', (string) $line->description);
        $this->assertTrue($line->prorated);
        $this->assertGreaterThan(0, $line->amount);

        $this->assertFalse(in_array($line->id(), self::$proratedLineItemIds));
        self::$proratedLineItemIds[] = $line->id();
    }

    /**
     * @depends testCreateWithAddons
     */
    public function testRemoveAddons(): void
    {
        // set to renew in the future
        $this->getEditOperation()->modify(self::$subscriptionWithAddons->clearCache(), [
            'renews_next' => strtotime('+1 month'),
        ]);

        // remove addons
        $this->getEditOperation()->modify(self::$subscriptionWithAddons, [
            'addons' => [],
        ]);

        // should create pending line items
        $lines = PendingLineItem::where('customer_id', self::$customer->id())
            ->sort('id DESC')
            ->first(2);

        $this->assertInstanceOf(PendingLineItem::class, $lines[0]);
        $this->assertStringContainsString('(removed 1)', (string) $lines[0]->description);
        $this->assertTrue($lines[0]->prorated);
        $this->assertLessThan(0, $lines[0]->amount);

        $this->assertInstanceOf(PendingLineItem::class, $lines[1]);
        $this->assertStringContainsString('(removed 2)', (string) $lines[1]->description);
        $this->assertTrue($lines[1]->prorated);
        $this->assertLessThan(0, $lines[1]->amount);

        foreach ($lines as $line) {
            $this->assertFalse(in_array($line->id(), self::$proratedLineItemIds));
            self::$proratedLineItemIds[] = $line->id();
        }
    }

    /**
     * @depends testRemoveAddons
     */
    public function testAddAddons(): void
    {
        // set to renew in the future
        $this->getEditOperation()->modify(self::$subscriptionWithAddons->clearCache(), [
            'renews_next' => strtotime('+1 month'),
        ]);

        // add an addon
        $this->getEditOperation()->modify(self::$subscriptionWithAddons, [
            'addons' => [
                [
                    'quantity' => 1,
                    'catalog_item' => self::$item->id,
                ],
            ],
        ]);

        // should create pending line items
        $line = PendingLineItem::where('customer_id', self::$customer->id())
            ->sort('id DESC')
            ->oneOrNull();

        $this->assertInstanceOf(PendingLineItem::class, $line);
        $this->assertStringContainsString('(added 1)', (string) $line->description);
        $this->assertTrue($line->prorated);
        $this->assertGreaterThan(0, $line->amount);

        $this->assertFalse(in_array($line->id(), self::$proratedLineItemIds));
        self::$proratedLineItemIds[] = $line->id();
    }

    /**
     * @depends testAddAddons
     */
    public function testChangeAddons(): void
    {
        // set to renew in the future
        $this->getEditOperation()->modify(self::$subscriptionWithAddons->clearCache(), [
            'renews_next' => strtotime('+1 month'),
        ]);

        // add an addon
        $addons = self::$subscriptionWithAddons->addons();
        ++$addons[0]['quantity'];
        $this->getEditOperation()->modify(self::$subscriptionWithAddons, [
            'addons' => $addons,
        ]);

        // should create pending line items
        $line = PendingLineItem::where('customer_id', self::$customer->id())
            ->sort('id DESC')
            ->one();

        $this->assertInstanceOf(PendingLineItem::class, $line);
        $this->assertStringContainsString('(added 1)', (string) $line->description);
        $this->assertTrue($line->prorated);
        $this->assertGreaterThan(0, $line->amount);

        $this->assertFalse(in_array($line->id(), self::$proratedLineItemIds));
        self::$proratedLineItemIds[] = $line->id();
    }

    /**
     * @depends testCreateWithAddons
     */
    public function testCannotAddInvalidAddons(): void
    {
        // add an addon
        $this->expectException(OperationException::class);
        $this->getEditOperation()->modify(self::$subscriptionWithAddons, [
            'addons' => [
                [
                    'quantity' => 1,
                    'plan' => self::$annualPlan->id,
                ],
            ],
        ]);
    }

    public function testChangeBillingCycle(): void
    {
        // remove any pending line items
        self::getService('test.database')->delete('LineItems', ['customer_id' => self::$customer->id()]);

        // create a fresh monthly subscription
        $subscription = $this->getCreateOperation()->create([
            'customer' => self::$customer,
            'plan' => self::$plan,
            'quantity' => 3,
        ]);

        // switch to an annual plan
        $t = CarbonImmutable::now()->setTime(0, 0)->getTimestamp();
        $this->getEditOperation()->modify($subscription, [
            'plan' => 'annual',
            'quantity' => 1,
        ]);

        // should start a new billing cycle
        $delta = abs($subscription->refresh()->renewed_last - $t);
        $this->assertLessThan(3, $delta);
        $this->assertGreaterThan(strtotime('+1 month'), $subscription->renews_next);

        // should create an invoice for the new cycle
        $invoice = Invoice::where('subscription_id', $subscription->id())
            ->sort('id DESC')
            ->oneOrNull();

        $this->assertInstanceOf(Invoice::class, $invoice);

        $lines = $invoice->items();
        $this->assertCount(1, $lines);
        $this->assertEquals(1564.5, $invoice->total);
        $this->assertEquals('Annual', $lines[0]['name']);
        $this->assertEquals(1490, $lines[0]['amount']);
        // the actual balance will vary depending on the
        // time the test suite is ran
        $this->assertGreaterThan(1200, $invoice->balance);
        $this->assertLessThan(1300, $invoice->balance);

        // Should create a credit note for the time remaining
        // on the original plan. Since the time remaining is the
        // full period this should be 3 x $99 + 5% tax
        $creditNote = CreditNote::where('customer', self::$customer->id())
            ->sort('id DESC')
            ->oneOrNull();

        $this->assertInstanceOf(CreditNote::class, $creditNote);
        // the actual balance will vary depending on the
        // time the test suite is ran
        $this->assertGreaterThan(300, $creditNote->total);
        $this->assertLessThan(400, $creditNote->total);
    }

    /**
     * @depends testCreateWithContractTerms
     */
    public function testRenewContract(): void
    {
        self::$subscriptionWithContract->pending_renewal = true;
        self::$subscriptionWithContract->saveOrFail();

        $newStart = self::$subscriptionWithContract->contract_period_end + 1;
        $newEnd = $newStart;
        for ($i = 0; $i < 5; ++$i) {
            $interval = new Interval(1, 'month');
            $newEnd = $interval->addTo($newEnd);
        }
        --$newEnd;
        $this->getBillOperation()->renewContract(self::$subscriptionWithContract);
        self::$subscriptionWithContract->saveOrFail();

        $this->assertEquals(5, self::$subscriptionWithContract->cycles);
        $this->assertEquals(1, self::$subscriptionWithContract->num_invoices);
        $this->assertFalse(self::$subscriptionWithContract->pending_renewal);
        $this->assertEquals(SubscriptionStatus::ACTIVE, self::$subscriptionWithContract->status);
        $this->assertEquals(Subscription::RENEWAL_MODE_RENEW_ONCE, self::$subscriptionWithContract->contract_renewal_mode);

        // should recalculate the contract period
        $this->assertTimestampsEqual((int) $newStart, (int) self::$subscriptionWithContract->contract_period_start);
        $this->assertTimestampsEqual((int) $newEnd, (int) self::$subscriptionWithContract->contract_period_end);
    }

    /**
     * @depends testCreate
     */
    public function testApprove(): void
    {
        $approval = (new ApproveSubscription())->approve(self::$subscription, '127.0.0.1', 'user-agent');

        // should build an approval
        $this->assertEquals('127.0.0.1', $approval->ip);
        $this->assertEquals('user-agent', $approval->user_agent);
        $this->assertEquals($approval->id(), self::$subscription->approval_id);
    }

    /**
     * @depends testCreate
     */
    public function testMetadata(): void
    {
        $metadata = self::$subscription->metadata;
        $metadata->test = true;
        $this->getEditOperation()->modify(self::$subscription, [
            'metadata' => $metadata,
        ]);
        $this->assertEquals((object) ['test' => true], self::$subscription->metadata);

        $this->getEditOperation()->modify(self::$subscription, [
            'metadata' => (object) ['internal.id' => '12345'],
        ]);
        $this->assertEquals((object) ['internal.id' => '12345'], self::$subscription->metadata);

        $this->getEditOperation()->modify(self::$subscription, [
            'metadata' => (object) ['array' => [], 'object' => new stdClass()],
        ]);
        $this->assertEquals((object) ['array' => [], 'object' => new stdClass()], self::$subscription->metadata);
    }

    /**
     * @depends testCreate
     */
    public function testBadMetadata(): void
    {
        $this->expectException(OperationException::class);
        $this->getEditOperation()->modify(self::$subscription, [
            'metadata' => (object) [str_pad('', 41) => 'fail'],
        ]);
    }

    /**
     * @depends testCreate
     */
    public function testBadMetadata2(): void
    {
        $this->expectException(OperationException::class);
        $this->getEditOperation()->modify(self::$subscription, [
            'metadata' => (object) ['fail' => str_pad('', 256)],
        ]);
    }

    /**
     * @depends testCreate
     */
    public function testBadMetadata3(): void
    {
        $this->expectException(OperationException::class);
        $this->getEditOperation()->modify(self::$subscription, [
            'metadata' => (object) array_fill(0, 11, 'fail'),
        ]);
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$subscription->delete());
    }

    public function testPendingInvoiceDoesNotTriggerNonpayment(): void
    {
        $subscription = $this->getCreateOperation()->create([
            'customer' => self::$autoCustomer,
            'plan' => self::$plan,
            'start_date' => strtotime('+1 month'),
        ]);

        // manually renew subscription to avoid triggering a charge
        CarbonImmutable::setTestNow('+1 year');
        /** @var Invoice $invoice */
        $invoice = $this->getBillOperation()->bill($subscription, false);
        $this->assertInstanceOf(Invoice::class, $invoice);

        // simulate 1 pending payment attempt
        // with no more attempts scheduled
        $pending = new Transaction();
        $pending->setInvoice($invoice);
        $pending->amount = $invoice->balance;
        $pending->status = Transaction::STATUS_PENDING;
        $this->assertTrue($pending->save());

        $invoice->attempt_count = 1;
        $invoice->next_payment_attempt = null;
        $this->assertTrue($invoice->save());

        // the subscription should be canceled
        $this->assertFalse($subscription->refresh()->canceled);
    }

    public function testCancelAfterNonpayment(): void
    {
        CarbonImmutable::setTestNow();
        self::$company->subscription_billing_settings->after_subscription_nonpayment = 'cancel';
        self::$company->subscription_billing_settings->saveOrFail();

        $subscription = $this->getCreateOperation()->create([
            'customer' => self::$autoCustomer,
            'plan' => self::$plan,
            'start_date' => strtotime('+1 month'),
        ]);

        // manually renew subscription to avoid triggering a charge
        CarbonImmutable::setTestNow('+1 year');
        /** @var Invoice $invoice */
        $invoice = $this->getBillOperation()->bill($subscription, false);
        $this->assertInstanceOf(Invoice::class, $invoice);

        // simulate 4 consecutive failed payment attempts
        // with no more attempts scheduled
        $invoice->skipSubscriptionUpdate(false);
        $invoice->attempt_count = 4;
        $invoice->next_payment_attempt = null;
        $invoice->saveOrFail();

        // the subscription should be canceled
        $this->assertTrue($subscription->refresh()->canceled);
        $this->assertEquals('nonpayment', $subscription->canceled_reason);

        // test another update with the subscription wrapped
        // in a transaction - this triggers INVD-1431
        $database = self::getService('test.database');
        $database->beginTransaction();

        $invoice->skipSubscriptionUpdate(false);
        $invoice->attempt_count = 5;
        $invoice->next_payment_attempt = null;
        $invoice->saveOrFail();

        $database->commit();

        // the subscription should be canceled
        $this->assertTrue($subscription->refresh()->canceled);
        $this->assertEquals('nonpayment', $subscription->canceled_reason);
    }

    public function testCancelAfterNonpaymentRefund(): void
    {
        self::$company->subscription_billing_settings->after_subscription_nonpayment = 'cancel';
        self::$company->subscription_billing_settings->saveOrFail();

        $subscription = $this->getCreateOperation()->create([
            'customer' => self::$autoCustomer,
            'plan' => self::$plan,
        ]);

        $invoice = Invoice::where('subscription_id', $subscription->id())->one();

        $payment = new Transaction();
        $payment->invoice = (int) $invoice->id();
        $payment->amount = $invoice->balance;
        $payment->saveOrFail();

        // simulate 4 consecutive failed payment attempts
        // with no more attempts scheduled
        $invoice->attempt_count = 4;
        $invoice->next_payment_attempt = null;
        $invoice->saveOrFail();

        $refund = new Transaction();
        $refund->type = Transaction::TYPE_REFUND;
        $refund->setParentTransaction($payment);
        $refund->invoice = (int) $invoice->id();
        $refund->amount = $payment->amount;
        $refund->saveOrFail();

        // the subscription should not be canceled since the payment
        // was refunded and is not considered a nonpayment
        $this->assertEquals(SubscriptionStatus::PAST_DUE, $subscription->refresh()->status);
    }

    public function testDoNothingAfterNonpayment(): void
    {
        CarbonImmutable::setTestNow();
        self::$company->subscription_billing_settings->after_subscription_nonpayment = 'do_nothing';
        self::$company->subscription_billing_settings->saveOrFail();

        $subscription = $this->getCreateOperation()->create([
            'customer' => self::$autoCustomer,
            'plan' => self::$plan,
            'start_date' => strtotime('+1 month'),
        ]);

        // manually renew subscription to avoid triggering a charge
        CarbonImmutable::setTestNow('+1 year');
        /** @var Invoice $invoice */
        $invoice = $this->getBillOperation()->bill($subscription, false);
        $this->assertInstanceOf(Invoice::class, $invoice);

        // simulate 4 consecutive failed payment attempts
        // with no more attempts scheduled
        $invoice->attempt_count = 4;
        $invoice->next_payment_attempt = null;
        $invoice->saveOrFail();

        // the subscription should NOT be canceled
        $this->assertFalse($subscription->refresh()->canceled);
        $this->assertTrue($subscription->persisted());
    }

    public function testDoNothingAfterNonpaymentContract(): void
    {
        self::$company->subscription_billing_settings->after_subscription_nonpayment = 'do_nothing';
        self::$company->subscription_billing_settings->saveOrFail();

        $myCustomer = new Customer();
        $myCustomer->autopay = true;
        $myCustomer->name = 'Testing';
        $myCustomer->saveOrFail();

        $subscription = $this->getCreateOperation()->create([
            'customer' => $myCustomer,
            'plan' => self::$plan,
            'cycles' => 12,
            'contract_renewal_mode' => Subscription::RENEWAL_MODE_MANUAL,
        ]);

        $invoice = Invoice::where('subscription_id', $subscription->id())->oneOrNull();
        $this->assertInstanceOf(Invoice::class, $invoice);

        // simulate 4 consecutive failed payment attempts
        // with no more attempts scheduled
        $invoice->attempt_count = 4;
        $invoice->next_payment_attempt = null;
        $invoice->saveOrFail();

        // the subscription should NOT be canceled
        $this->assertFalse($subscription->refresh()->canceled);
        $this->assertTrue($subscription->persisted());
    }

    public function testInactiveCustomer(): void
    {
        $this->expectException(OperationException::class);
        $this->expectExceptionMessage('This cannot be created because the customer is inactive');
        $this->getCreateOperation()->create([
            'customer' => self::$inactiveCustomer,
            'plan' => self::$plan,
            'start_date' => (int) mktime(0, 0, 0, (int) date('m'), (int) date('d'), (int) date('Y')),
        ]);
    }

    /**
     * Assert that subscriptions w/ non-custom plans should have a null amount value.
     */
    public function testNonCustomPlanAmount(): void
    {
        $this->expectException(OperationException::class);
        $this->expectExceptionMessage('Amounts are only allowed when the plan has a custom pricing mode');

        $plan = new Plan();
        $plan->name = 'Test';
        $plan->amount = 1;
        $plan->pricing_mode = Plan::PRICING_PER_UNIT;
        $plan->interval = Interval::MONTH;
        $plan->interval_count = 1;
        $plan->saveOrFail();

        $this->getCreateOperation()->create([
            'customer' => self::$customer,
            'plan' => $plan,
            'amount' => 20,
            'start_date' => (int) mktime(0, 0, 0, (int) date('m'), (int) date('d'), (int) date('Y')),
        ]);
    }

    /**
     * Assert that subscriptions w/ custom plans should have a non-null amount value.
     */
    public function testAmountWithCustomPlan(): void
    {
        $this->expectException(OperationException::class);
        $this->expectExceptionMessage('An amount is required when the subscription has a custom plan');

        $plan = new Plan();
        $plan->name = 'Test';
        $plan->pricing_mode = Plan::PRICING_CUSTOM;
        $plan->interval = Interval::MONTH;
        $plan->interval_count = 1;
        $plan->saveOrFail();

        $this->getCreateOperation()->create([
            'customer' => self::$customer,
            'plan' => $plan,
            'start_date' => (int) mktime(0, 0, 0, (int) date('m'), (int) date('d'), (int) date('Y')),
        ]);
    }

    public function testQuantityWithCustomPlan(): void
    {
        $plan = new Plan();
        $plan->name = 'Test';
        $plan->pricing_mode = Plan::PRICING_CUSTOM;
        $plan->interval = Interval::MONTH;
        $plan->interval_count = 1;
        $plan->saveOrFail();

        $subscription = $this->getCreateOperation()->create([
            'customer' => self::$customer,
            'plan' => $plan,
            'amount' => 100,
            'quantity' => 2,
            'start_date' => (int) mktime(0, 0, 0, (int) date('m'), (int) date('d'), (int) date('Y')),
        ]);
        $this->assertEquals(200, $subscription->mrr);
        $this->assertEquals(210, $subscription->recurring_total);
    }
}
