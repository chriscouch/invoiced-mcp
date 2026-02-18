<?php

namespace App\Tests\SubscriptionBilling\Operations;

use App\AccountsReceivable\Models\Coupon;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\ShippingDetail;
use App\Core\Utils\Enums\ObjectType;
use App\Core\Utils\ValueObjects\Interval;
use App\Metadata\Models\CustomField;
use App\PaymentProcessing\Models\Card;
use App\Sending\Email\Models\EmailTemplate;
use App\Sending\Email\Models\EmailTemplateOption;
use App\SubscriptionBilling\Models\PendingLineItem;
use App\SubscriptionBilling\Models\Plan;
use App\SubscriptionBilling\Models\Subscription;
use App\SubscriptionBilling\Operations\BillSubscription;
use App\SubscriptionBilling\ValueObjects\SubscriptionStatus;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use stdClass;

class BillSubscriptionTest extends AppTestCase
{
    private static int $start;
    private static Plan $plan2;
    private static Subscription $subscriptionContractManualRenewal;
    private static Subscription $subscriptionContractRenewOnce;
    private static Subscription $subscriptionContractAutoRenewal;
    private static Subscription $subscriptionToBeCanceled1;
    private static Subscription $subscriptionToBeCanceled2;
    private static Subscription $subscriptionToBeCanceled3;
    private static Subscription $subscriptionPaused;
    private static Subscription $subscriptionExpiringCoupon;
    private static Subscription $subscriptionArrears;
    private static Subscription $subscriptionArrearsCalendarBilling;
    private static Subscription $subscriptionAdvance0;
    private static Subscription $subscriptionAdvance14;
    private static Subscription $subscriptionAdvanceCalendarBilling;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasPlan();

        self::$plan2 = new Plan();
        self::$plan2->id = 'monthly';
        self::$plan2->name = 'Monthly Plan';
        self::$plan2->amount = 100;
        self::$plan2->interval = Interval::MONTH;
        self::$plan2->interval_count = 1;
        self::$plan2->saveOrFail();

        // enable manual contract renewals for testing
        self::$company->features->enable('subscription_manual_renewal');

        $customField1 = new CustomField();
        $customField1->id = 'account-rep';
        $customField1->object = ObjectType::Invoice->typeName();
        $customField1->name = 'Account Rep';
        $customField1->saveOrFail();

        // Watch out for the 2030 bug, when Jan 1, 2030 rolls
        // around these tests will no longer work ;)
        self::$start = (new CarbonImmutable('2030-01-01'))->getTimestamp();

        $createSubscription = self::getService('test.create_subscription');
        self::$subscription = $createSubscription->create([
            'customer' => self::$customer,
            'plan' => self::$plan,
            'quantity' => 2,
            'start_date' => self::$start,
            'metadata' => (object) ['account-rep' => 'Jan'],
        ]);

        $shipTo = new ShippingDetail();
        $shipTo->subscription_id = (int) self::$subscription->id();
        $shipTo->name = 'Test';
        $shipTo->address1 = '1234 main st';
        $shipTo->city = 'Austin';
        $shipTo->state = 'TX';
        $shipTo->postal_code = '78701';
        $shipTo->country = 'US';
        $shipTo->saveOrFail();

        self::$subscriptionContractManualRenewal = $createSubscription->create([
            'customer' => self::$customer,
            'plan' => self::$plan,
            'start_date' => self::$start,
            'cycles' => 4,
            'contract_renewal_mode' => Subscription::RENEWAL_MODE_MANUAL,
        ]);

        self::$subscriptionContractAutoRenewal = $createSubscription->create([
            'customer' => self::$customer,
            'plan' => self::$plan,
            'start_date' => self::$start,
            'cycles' => 4,
            'contract_renewal_mode' => Subscription::RENEWAL_MODE_AUTO,
        ]);

        self::$subscriptionContractRenewOnce = $createSubscription->create([
            'customer' => self::$customer,
            'plan' => self::$plan,
            'start_date' => self::$start,
            'cycles' => 4,
            'contract_renewal_mode' => Subscription::RENEWAL_MODE_RENEW_ONCE,
        ]);

        self::$subscriptionToBeCanceled1 = $createSubscription->create([
            'customer' => self::$customer,
            'plan' => self::$plan,
            'start_date' => self::$start,
            'cancel_at_period_end' => true,
            'contract_renewal_mode' => Subscription::RENEWAL_MODE_RENEW_ONCE,
        ]);

        self::$subscriptionToBeCanceled2 = $createSubscription->create([
            'customer' => self::$customer,
            'plan' => self::$plan,
            'start_date' => self::$start,
            'cycles' => 4,
            'cancel_at_period_end' => true,
        ]);

        self::$subscriptionToBeCanceled3 = $createSubscription->create([
            'customer' => self::$customer,
            'plan' => self::$plan,
            'start_date' => self::$start,
            'cycles' => 1,
            'contract_renewal_mode' => Subscription::RENEWAL_MODE_AUTO,
        ]);

        self::$subscriptionPaused = $createSubscription->create([
            'customer' => self::$customer,
            'plan' => self::$plan,
            'start_date' => self::$start,
            'cycles' => 1,
            'contract_renewal_mode' => Subscription::RENEWAL_MODE_AUTO,
            'paused' => true,
        ]);

        $expiringCoupon = new Coupon();
        $expiringCoupon->id = 'expiring-coupon';
        $expiringCoupon->name = 'Expiring Coupon';
        $expiringCoupon->value = 10;
        $expiringCoupon->duration = 1;
        $expiringCoupon->saveOrFail();

        self::$subscriptionExpiringCoupon = $createSubscription->create([
            'customer' => self::$customer,
            'plan' => self::$plan,
            'start_date' => self::$start,
            'discounts' => [$expiringCoupon->id],
        ]);

        self::$subscriptionArrears = $createSubscription->create([
            'customer' => self::$customer,
            'plan' => self::$plan,
            'start_date' => mktime(0, 0, 0, 9, 1, 2028),
            'bill_in' => Subscription::BILL_IN_ARREARS,
            'cycles' => 7,
        ]);

        self::$subscriptionArrearsCalendarBilling = $createSubscription->create([
            'customer' => self::$customer,
            'plan' => self::$plan2,
            'start_date' => mktime(7, 8, 9, 8, 17, 2028),
            'bill_in' => Subscription::BILL_IN_ARREARS,
            'cycles' => 7,
            'snap_to_nth_day' => 1,
        ]);

        self::$subscriptionAdvance0 = $createSubscription->create([
            'customer' => self::$customer,
            'plan' => self::$plan,
            'start_date' => mktime(0, 0, 0, 9, 1, 2028),
        ]);

        self::$subscriptionAdvance14 = $createSubscription->create([
            'customer' => self::$customer,
            'plan' => self::$plan,
            'start_date' => mktime(0, 0, 0, 9, 1, 2028),
            'bill_in_advance_days' => 14,
        ]);

        self::$subscriptionAdvanceCalendarBilling = $createSubscription->create([
            'customer' => self::$customer,
            'plan' => self::$plan2,
            'start_date' => mktime(7, 8, 9, 8, 17, 2028),
            'snap_to_nth_day' => 1,
        ]);
    }

    private function getOperation(): BillSubscription
    {
        return self::getService('test.bill_subscription');
    }

    public function testShouldSendInvoice(): void
    {
        $operation = $this->getOperation();
        $invoice = new Invoice();
        $invoice->date = time();
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->total = 100;

        $this->assertTrue($operation->shouldSendInvoice($invoice));

        $invoice->autopay = true;
        $invoice->customer = -1;
        $customer = new Customer();
        $invoice->setRelation('customer', $customer);
        $this->assertTrue($operation->shouldSendInvoice($invoice));

        $customer->setPaymentSource(new Card());
        $this->assertFalse($operation->shouldSendInvoice($invoice));

        $invoice->autopay = false;
        $emailTemplate = EmailTemplate::make(self::$company->id, EmailTemplate::NEW_INVOICE);
        $emailTemplate->options = [EmailTemplateOption::SEND_ON_SUBSCRIPTION_INVOICE => false];
        $emailTemplate->saveOrFail();
        $this->assertFalse($operation->shouldSendInvoice($invoice));

        $emailTemplate->options = [EmailTemplateOption::SEND_ON_SUBSCRIPTION_INVOICE => true];
        $emailTemplate->saveOrFail();
        $this->assertTrue($operation->shouldSendInvoice($invoice));

        $invoice->draft = true;
        $this->assertFalse($operation->shouldSendInvoice($invoice));

        $invoice->draft = false;
        $invoice->date = strtotime('-33 days');
        $this->assertFalse($operation->shouldSendInvoice($invoice));
    }

    public function testBill(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::createFromTimestamp((int) self::$subscription->renews_next));
        /** @var Invoice $newInvoice */
        $newInvoice = $this->getOperation()->bill(self::$subscription, false);

        $this->assertInstanceOf(Invoice::class, $newInvoice);

        $expected = [
            'id' => $newInvoice->id(),
            'customer' => self::$customer->id(),
            'company' => self::$company->id(),
            'payment_plan' => null,
            'name' => 'Starter',
            'number' => 'INV-00001',
            'currency' => 'usd',
            'date' => self::$start,
            'payment_terms' => null,
            'due_date' => null,
            'items' => [
                [
                    'plan' => 'starter',
                    'catalog_item' => null,
                    'type' => 'plan',
                    'name' => 'Starter',
                    'subscription' => self::$subscription->id(),
                    'period_start' => mktime(0, 0, 0, 1, 1, 2030),
                    'period_end' => mktime(0, 0, 0, 3, 1, 2030) - 1,
                    'prorated' => false,
                    'description' => '',
                    'quantity' => 2.0,
                    'unit_cost' => 100.0,
                    'amount' => 200.0,
                    'discountable' => true,
                    'discounts' => [],
                    'taxable' => true,
                    'taxes' => [],
                    'metadata' => new stdClass(),
                ],
            ],
            'shipping' => [],
            'taxes' => [],
            'discounts' => [],
            'subtotal' => 200.0,
            'total' => 200.0,
            'balance' => 200.0,
            'chase' => false,
            'notes' => null,
            'purchase_order' => null,
            'draft' => false,
            'closed' => false,
            'paid' => false,
            'next_chase_on' => null,
            'needs_attention' => false,
            'autopay' => false,
            'payment_source' => null,
            'attempt_count' => 0,
            'next_payment_attempt' => null,
            'ship_to' => [
                'address1' => '1234 main st',
                'address2' => null,
                'attention_to' => null,
                'city' => 'Austin',
                'country' => 'US',
                'name' => 'Test',
                'postal_code' => '78701',
                'state' => 'TX',
            ],
            'metadata' => (object) ['account-rep' => 'Jan'],
            'late_fees' => true,
            'network_document_id' => null,
            'subscription_id' => self::$subscription->id(),
        ];

        $arr = $newInvoice->toArray();
        foreach (['created_at', 'updated_at', 'object', 'status', 'last_sent', 'url', 'payment_url', 'pdf_url', 'csv_url'] as $property) {
            unset($arr[$property]);
        }
        $arr['company'] = $newInvoice->tenant_id;
        foreach ($arr['items'] as &$item) {
            unset($item['id']);
            unset($item['created_at']);
            unset($item['updated_at']);
            unset($item['object']);
        }
        unset($arr['ship_to']['created_at']);
        unset($arr['ship_to']['updated_at']);
        $this->assertEquals($expected, $arr);

        $this->assertTimestampsEqual((int) strtotime('+2 months', self::$start), (int) self::$subscription->renews_next);
        $this->assertTimestampsEqual($newInvoice->date, (int) self::$subscription->renewed_last);

        /** @var ShippingDetail $shipTo */
        $shipTo = $newInvoice->ship_to;
        $this->assertInstanceOf(ShippingDetail::class, $shipTo);
        $this->assertEquals('1234 main st', $shipTo->address1);
    }

    public function testBillWithCancellation(): void
    {
        CarbonImmutable::setTestNow('@'.self::$subscriptionToBeCanceled1->renews_next);
        $this->assertNull($this->getOperation()->bill(self::$subscriptionToBeCanceled1, true));
        $this->assertTrue(self::$subscriptionToBeCanceled1->canceled);
        $this->assertEquals(SubscriptionStatus::CANCELED, self::$subscriptionToBeCanceled1->status);
    }

    public function testBillWithCancellationContractBilling(): void
    {
        // the contract term needs to be billed out until it is canceled
        $operation = $this->getOperation();
        for ($i = 0; $i < 4; ++$i) {
            CarbonImmutable::setTestNow('@'.self::$subscriptionToBeCanceled2->renews_next);
            $invoice = $operation->bill(self::$subscriptionToBeCanceled2, true);
            $this->assertInstanceOf(Invoice::class, $invoice);
        }

        // after the contract is finished it should be marked as canceled instead of finished
        $this->assertTrue(self::$subscriptionToBeCanceled2->canceled);
        $this->assertFalse(self::$subscriptionToBeCanceled2->finished);
        $this->assertEquals(SubscriptionStatus::CANCELED, self::$subscriptionToBeCanceled2->status);
    }

    public function testBillWithCancellationInLastTermContractBilling(): void
    {
        $operation = $this->getOperation();
        CarbonImmutable::setTestNow('@'.self::$subscriptionToBeCanceled3->renews_next);
        $invoice = $operation->bill(self::$subscriptionToBeCanceled3, true);
        $this->assertInstanceOf(Invoice::class, $invoice);

        // cancelling at the end of the last term needs to be billed out until it is canceled
        self::$subscriptionToBeCanceled3->cancel_at_period_end = true;
        self::$subscriptionToBeCanceled3->saveOrFail();

        CarbonImmutable::setTestNow('@'.self::$subscriptionToBeCanceled3->renews_next);
        $this->assertNull($operation->bill(self::$subscriptionToBeCanceled3, true));

        // after the contract is finished it should be marked as canceled instead of finished
        $this->assertTrue(self::$subscriptionToBeCanceled3->canceled);
        $this->assertFalse(self::$subscriptionToBeCanceled3->finished);
        $this->assertEquals(SubscriptionStatus::CANCELED, self::$subscriptionToBeCanceled3->status);
    }

    public function testBillFinishContractTermManualRenewal(): void
    {
        // the contract term needs to be billed out until it is canceled
        $operation = $this->getOperation();
        for ($i = 0; $i < 4; ++$i) {
            CarbonImmutable::setTestNow(CarbonImmutable::createFromTimestamp((int) self::$subscriptionContractManualRenewal->renews_next));
            $invoice = $operation->bill(self::$subscriptionContractManualRenewal, true);
            $this->assertInstanceOf(Invoice::class, $invoice);
        }

        // after the subscription is finished it should be marked for pending renewal
        $this->assertFalse(self::$subscriptionContractManualRenewal->canceled);
        $this->assertFalse(self::$subscriptionContractManualRenewal->finished);
        $this->assertFalse(self::$subscriptionContractManualRenewal->pending_renewal);
        $this->assertEquals(SubscriptionStatus::ACTIVE, self::$subscriptionContractManualRenewal->status);
    }

    public function testBillManualPendingRenewal(): void
    {
        CarbonImmutable::setTestNow(new CarbonImmutable('2038-01-01'));

        $operation = $this->getOperation();
        $subscription = self::$subscriptionContractManualRenewal;
        $subscription->num_invoices = 1;
        $subscription->cycles = 1;
        $subscription->renews_next = (new CarbonImmutable('-1 second'))->getTimestamp();
        $subscription->contract_period_end = (new CarbonImmutable('-1 day'))->getTimestamp();
        $subscription->saveOrFail();
        $invoice = $operation->bill($subscription, true);
        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertTrue($subscription->pending_renewal);

        $subscription->renews_next = (new CarbonImmutable('-1 second'))->getTimestamp();
        $subscription->contract_period_end = (new CarbonImmutable('+1 day'))->getTimestamp();
        $subscription->saveOrFail();
        $invoice = $operation->bill($subscription, true);
        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertFalse($subscription->pending_renewal);
    }

    public function testBillFinishContractTermRenewOnce(): void
    {
        // the contract term needs to be billed out until the contract is finished
        $operation = $this->getOperation();
        for ($i = 0; $i < 5; ++$i) {
            CarbonImmutable::setTestNow(CarbonImmutable::createFromTimestamp((int) self::$subscriptionContractRenewOnce->renews_next));
            $invoice = $operation->bill(self::$subscriptionContractRenewOnce, true);
            $this->assertInstanceOf(Invoice::class, $invoice);
        }

        // after the subscription is finished it should be marked for pending renewal
        $this->assertFalse(self::$subscriptionContractRenewOnce->canceled);
        $this->assertFalse(self::$subscriptionContractRenewOnce->finished);
        $this->assertFalse(self::$subscriptionContractRenewOnce->pending_renewal);
        $this->assertEquals(SubscriptionStatus::ACTIVE, self::$subscriptionContractRenewOnce->status);
        $this->assertGreaterThan(0, self::$subscriptionContractRenewOnce->renews_next);
        $this->assertEquals(Subscription::RENEWAL_MODE_MANUAL, self::$subscriptionContractRenewOnce->contract_renewal_mode);
    }

    public function testBillFinishContractTermAutoRenewal(): void
    {
        // the contract term needs to be billed out until the contract is finished
        $operation = $this->getOperation();
        for ($i = 0; $i < 5; ++$i) {
            CarbonImmutable::setTestNow(CarbonImmutable::createFromTimestamp((int) self::$subscriptionContractAutoRenewal->renews_next));
            $invoice = $operation->bill(self::$subscriptionContractAutoRenewal, true);
            $this->assertInstanceOf(Invoice::class, $invoice);
        }

        // after the subscription is finished it should be auto-renewed
        $this->assertFalse(self::$subscriptionContractAutoRenewal->canceled);
        $this->assertFalse(self::$subscriptionContractAutoRenewal->finished);
        $this->assertFalse(self::$subscriptionContractAutoRenewal->pending_renewal);
        $this->assertEquals(SubscriptionStatus::ACTIVE, self::$subscriptionContractAutoRenewal->status);
        $this->assertGreaterThan(0, self::$subscriptionContractAutoRenewal->renews_next);
        $this->assertTimestampsEqual((int) strtotime('+8 months', self::$start), (int) self::$subscriptionContractAutoRenewal->contract_period_start);
        $this->assertTimestampsEqual((int) strtotime('+8 months', (int) self::$subscriptionContractAutoRenewal->contract_period_start) - 1, (int) self::$subscriptionContractAutoRenewal->contract_period_end);
    }

    public function testBillWithPending(): void
    {
        // create pending line items
        $line = new PendingLineItem();
        $line->setParent(self::$customer);
        $line->name = 'Line 1';
        $line->unit_cost = 100;
        $this->assertTrue($line->save());

        $line2 = new PendingLineItem();
        $line2->setParent(self::$customer);
        $line2->name = 'Line 2';
        $line2->quantity = 2;
        $line2->unit_cost = 150;
        $this->assertTrue($line2->save());

        // bill subscription
        CarbonImmutable::setTestNow('@'.self::$subscription->renews_next);
        /** @var Invoice $newInvoice */
        $newInvoice = $this->getOperation()->bill(self::$subscription, false);

        // verify line items were added to invoice
        $this->assertInstanceOf(Invoice::class, $newInvoice);

        $expected = [
            [
                'plan' => 'starter',
                'type' => 'plan',
                'catalog_item' => null,
                'subscription' => self::$subscription->id(),
                'period_start' => mktime(0, 0, 0, 3, 1, 2030),
                'period_end' => mktime(0, 0, 0, 5, 1, 2030) - 1,
                'prorated' => false,
                'name' => 'Starter',
                'description' => '',
                'quantity' => 2.0,
                'unit_cost' => 100.0,
                'amount' => 200.0,
                'discountable' => true,
                'discounts' => [],
                'taxable' => true,
                'taxes' => [],
                'metadata' => new stdClass(),
            ],
            [
                'type' => null,
                'name' => 'Line 1',
                'description' => null,
                'quantity' => 1.0,
                'unit_cost' => 100.0,
                'amount' => 100.0,
                'catalog_item' => null,
                'discountable' => true,
                'discounts' => [],
                'taxable' => true,
                'taxes' => [],
                'metadata' => new stdClass(),
            ],
            [
                'type' => null,
                'name' => 'Line 2',
                'description' => null,
                'quantity' => 2.0,
                'unit_cost' => 150.0,
                'amount' => 300.0,
                'catalog_item' => null,
                'discountable' => true,
                'discounts' => [],
                'taxable' => true,
                'taxes' => [],
                'metadata' => new stdClass(),
            ],
        ];

        $items = $newInvoice->items();
        foreach ($items as &$item) {
            unset($item['id']);
            unset($item['created_at']);
            unset($item['updated_at']);
            unset($item['object']);
        }

        $this->assertCount(3, $items);
        $this->assertEquals($expected, $items);

        $this->assertEquals(600, $newInvoice->total);

        // pending line items should no longer exist
        $this->assertNull(PendingLineItem::find($line->id()));
        $this->assertNull(PendingLineItem::find($line2->id()));
    }

    public function testBillStaleSubscription(): void
    {
        CarbonImmutable::setTestNow();
        $subscription = self::getService('test.create_subscription')->create([
            'customer' => self::$customer,
            'plan' => self::$plan,
            'start_date' => strtotime('+1 day'),
            'cycles' => 1,
        ]);
        $staleSubscription = Subscription::findOrFail($subscription->id());

        $operation = $this->getOperation();

        CarbonImmutable::setTestNow('+2 days');
        $newInvoice = $operation->bill($subscription, false);
        $this->assertInstanceOf(Invoice::class, $newInvoice);
        $this->assertTrue($subscription->finished);

        CarbonImmutable::setTestNow('+2 days');
        $this->assertNull($operation->bill($staleSubscription, false), 'The stale subscription should not have been billed.');
    }

    public function testBillPausedSubscription(): void
    {
        $operation = $this->getOperation();
        $this->assertNull($operation->bill(self::$subscriptionPaused));
    }

    public function testBillWithExpiringDiscount(): void
    {
        $this->assertEquals(45, self::$subscriptionExpiringCoupon->mrr);
        $this->assertEquals(90, self::$subscriptionExpiringCoupon->recurring_total);

        $operation = $this->getOperation();

        CarbonImmutable::setTestNow('@'.self::$subscriptionExpiringCoupon->renews_next);
        $newInvoice = $operation->bill(self::$subscriptionExpiringCoupon, false);
        $this->assertInstanceOf(Invoice::class, $newInvoice);

        $this->assertEquals(50, self::$subscriptionExpiringCoupon->mrr);
        $this->assertEquals(100, self::$subscriptionExpiringCoupon->recurring_total);
    }

    public function testBillInArrears(): void
    {
        $operation = $this->getOperation();
        for ($i = 0; $i < 7; ++$i) {
            CarbonImmutable::setTestNow(CarbonImmutable::createFromTimestamp((int) self::$subscriptionArrears->renews_next));
            $operation->bill(self::$subscriptionArrears, false);
        }

        $dates = $this->buildDateHistory(self::$subscriptionArrears);
        $expected = [
            [
                'date' => '2028-10-31',
                'period_start' => '2028-09-01',
                'period_end' => '2028-10-31',
            ],
            [
                'date' => '2028-12-31',
                'period_start' => '2028-11-01',
                'period_end' => '2028-12-31',
            ],
            [
                'date' => '2029-02-28',
                'period_start' => '2029-01-01',
                'period_end' => '2029-02-28',
            ],
            [
                'date' => '2029-04-30',
                'period_start' => '2029-03-01',
                'period_end' => '2029-04-30',
            ],
            [
                'date' => '2029-06-30',
                'period_start' => '2029-05-01',
                'period_end' => '2029-06-30',
            ],
            [
                'date' => '2029-08-31',
                'period_start' => '2029-07-01',
                'period_end' => '2029-08-31',
            ],
            [
                'date' => '2029-10-31',
                'period_start' => '2029-09-01',
                'period_end' => '2029-10-31',
            ],
        ];
        $this->assertEquals($expected, $dates);
    }

    public function testBillInArrearsCalendarBilling(): void
    {
        $operation = $this->getOperation();
        for ($i = 0; $i < 7; ++$i) {
            CarbonImmutable::setTestNow(CarbonImmutable::createFromTimestamp((int) self::$subscriptionArrearsCalendarBilling->renews_next));
            $operation->bill(self::$subscriptionArrearsCalendarBilling, false);
        }

        $dates = $this->buildDateHistory(self::$subscriptionArrearsCalendarBilling);
        $expected = [
            [
                'date' => '2028-08-31',
                'period_start' => '2028-08-17',
                'period_end' => '2028-08-31',
            ],
            [
                'date' => '2028-09-30',
                'period_start' => '2028-09-01',
                'period_end' => '2028-09-30',
            ],
            [
                'date' => '2028-10-31',
                'period_start' => '2028-10-01',
                'period_end' => '2028-10-31',
            ],
            [
                'date' => '2028-11-30',
                'period_start' => '2028-11-01',
                'period_end' => '2028-11-30',
            ],
            [
                'date' => '2028-12-31',
                'period_start' => '2028-12-01',
                'period_end' => '2028-12-31',
            ],
            [
                'date' => '2029-01-31',
                'period_start' => '2029-01-01',
                'period_end' => '2029-01-31',
            ],
            [
                'date' => '2029-02-28',
                'period_start' => '2029-02-01',
                'period_end' => '2029-02-28',
            ],
        ];
        $this->assertEquals($expected, $dates);
    }

    public function testBillInAdvance(): void
    {
        $operation = $this->getOperation();
        for ($i = 0; $i < 7; ++$i) {
            CarbonImmutable::setTestNow(CarbonImmutable::createFromTimestamp((int) self::$subscriptionAdvance0->renews_next));
            $operation->bill(self::$subscriptionAdvance0, false);
        }

        $dates = $this->buildDateHistory(self::$subscriptionAdvance0);
        $expected = [
            [
                'date' => '2028-09-01',
                'period_start' => '2028-09-01',
                'period_end' => '2028-10-31',
            ],
            [
                'date' => '2028-11-01',
                'period_start' => '2028-11-01',
                'period_end' => '2028-12-31',
            ],
            [
                'date' => '2029-01-01',
                'period_start' => '2029-01-01',
                'period_end' => '2029-02-28',
            ],
            [
                'date' => '2029-03-01',
                'period_start' => '2029-03-01',
                'period_end' => '2029-04-30',
            ],
            [
                'date' => '2029-05-01',
                'period_start' => '2029-05-01',
                'period_end' => '2029-06-30',
            ],
            [
                'date' => '2029-07-01',
                'period_start' => '2029-07-01',
                'period_end' => '2029-08-31',
            ],
            [
                'date' => '2029-09-01',
                'period_start' => '2029-09-01',
                'period_end' => '2029-10-31',
            ],
        ];
        $this->assertEquals($expected, $dates);
    }

    public function testBillInAdvance14(): void
    {
        $operation = $this->getOperation();
        for ($i = 0; $i < 7; ++$i) {
            CarbonImmutable::setTestNow(CarbonImmutable::createFromTimestamp((int) self::$subscriptionAdvance14->renews_next));
            $operation->bill(self::$subscriptionAdvance14, false);
        }

        $dates = $this->buildDateHistory(self::$subscriptionAdvance14);
        $expected = [
            [
                'date' => '2028-08-18',
                'period_start' => '2028-09-01',
                'period_end' => '2028-10-31',
            ],
            [
                'date' => '2028-10-18',
                'period_start' => '2028-11-01',
                'period_end' => '2028-12-31',
            ],
            [
                'date' => '2028-12-18',
                'period_start' => '2029-01-01',
                'period_end' => '2029-02-28',
            ],
            [
                'date' => '2029-02-15',
                'period_start' => '2029-03-01',
                'period_end' => '2029-04-30',
            ],
            [
                'date' => '2029-04-17',
                'period_start' => '2029-05-01',
                'period_end' => '2029-06-30',
            ],
            [
                'date' => '2029-06-17',
                'period_start' => '2029-07-01',
                'period_end' => '2029-08-31',
            ],
            [
                'date' => '2029-08-18',
                'period_start' => '2029-09-01',
                'period_end' => '2029-10-31',
            ],
        ];
        $this->assertEquals($expected, $dates);
    }

    public function testBillInAdvanceCalendarBilling(): void
    {
        $operation = $this->getOperation();
        for ($i = 0; $i < 7; ++$i) {
            CarbonImmutable::setTestNow(CarbonImmutable::createFromTimestamp((int) self::$subscriptionAdvanceCalendarBilling->renews_next));
            $operation->bill(self::$subscriptionAdvanceCalendarBilling, false);
        }

        $dates = $this->buildDateHistory(self::$subscriptionAdvanceCalendarBilling);
        $expected = [
            [
                'date' => '2028-08-17',
                'period_start' => '2028-08-17',
                'period_end' => '2028-08-31',
            ],
            [
                'date' => '2028-09-01',
                'period_start' => '2028-09-01',
                'period_end' => '2028-09-30',
            ],
            [
                'date' => '2028-10-01',
                'period_start' => '2028-10-01',
                'period_end' => '2028-10-31',
            ],
            [
                'date' => '2028-11-01',
                'period_start' => '2028-11-01',
                'period_end' => '2028-11-30',
            ],
            [
                'date' => '2028-12-01',
                'period_start' => '2028-12-01',
                'period_end' => '2028-12-31',
            ],
            [
                'date' => '2029-01-01',
                'period_start' => '2029-01-01',
                'period_end' => '2029-01-31',
            ],
            [
                'date' => '2029-02-01',
                'period_start' => '2029-02-01',
                'period_end' => '2029-02-28',
            ],
        ];
        $this->assertEquals($expected, $dates);
    }

    private function buildDateHistory(Subscription $subscription): array
    {
        $result = [];
        $invoices = Invoice::where('subscription_id', $subscription->id())
            ->sort('id ASC')
            ->all();
        foreach ($invoices as $invoice) {
            $result[] = [
                'date' => date('Y-m-d', $invoice->date),
                'period_start' => date('Y-m-d', $invoice->items[0]->period_start),
                'period_end' => date('Y-m-d', $invoice->items[0]->period_end),
            ];
        }

        return $result;
    }
}
