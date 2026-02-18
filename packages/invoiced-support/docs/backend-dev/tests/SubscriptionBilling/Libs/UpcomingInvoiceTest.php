<?php

namespace App\Tests\SubscriptionBilling\Libs;

use App\AccountsReceivable\Enums\InvoiceStatus;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\SubscriptionBilling\Libs\UpcomingInvoice;
use App\SubscriptionBilling\Models\PendingLineItem;
use App\SubscriptionBilling\Models\Subscription;
use App\Tests\AppTestCase;
use stdClass;

class UpcomingInvoiceTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasPlan();
        self::hasSubscription();

        self::$customer->payment_terms = 'NET 12';
        self::$customer->save();

        self::$subscription->period_end = (int) gmmktime(23, 59, 59, 6, 11, 2028);
        self::$subscription->renews_next = (int) gmmktime(0, 0, 0, 6, 12, 2028);
        self::$subscription->save();
    }

    public function testGetCustomer(): void
    {
        $upcoming = $this->getUpcoming();
        $this->assertEquals(self::$customer, $upcoming->getCustomer());
    }

    public function testSetSubscription(): void
    {
        $upcoming = $this->getUpcoming();
        $sub = new Subscription();
        $upcoming->setSubscription($sub);
        $this->assertEquals($sub, $upcoming->getSubscription());
    }

    public function testGetSubscription(): void
    {
        $upcoming = $this->getUpcoming();
        /** @var Subscription $subscription */
        $subscription = $upcoming->getSubscription();
        $this->assertInstanceOf(Subscription::class, $subscription);
        $this->assertEquals(self::$subscription->id(), $subscription->id());
    }

    public function testGetSubscriptionNone(): void
    {
        $customer = new Customer();
        $customer->tenant_id = (int) self::$company->id();
        $upcoming = new UpcomingInvoice($customer);
        $this->assertNull($upcoming->getSubscription());
    }

    public function testBuildSubscription(): void
    {
        $upcoming = $this->getUpcoming();

        $invoice = $upcoming->build();

        $this->assertInstanceOf(Invoice::class, $invoice);

        $expected = [
            'id' => null,
            'object' => 'invoice',
            'customer' => self::$customer->id(),
            'name' => 'Starter',
            'currency' => 'usd',
            'items' => [
                [
                    'type' => 'plan',
                    'plan' => 'starter',
                    'catalog_item' => null,
                    'subscription' => self::$subscription->id(),
                    'period_start' => gmmktime(0, 0, 0, 6, 12, 2028),
                    'period_end' => gmmktime(23, 59, 59, 8, 11, 2028),
                    'prorated' => false,
                    'name' => 'Starter',
                    'description' => '',
                    'quantity' => 1.0,
                    'unit_cost' => 100.0,
                    'amount' => 100.0,
                    'taxes' => [],
                    'discounts' => [],
                    'taxable' => true,
                    'discountable' => true,
                    'metadata' => new stdClass(),
                ],
            ],
            'discounts' => [],
            'shipping' => [],
            'taxes' => [],
            'subtotal' => 100.0,
            'total' => 100.0,
            'balance' => 0,
            'notes' => null,
            'number' => null,
            'date' => gmmktime(0, 0, 0, 6, 12, 2028),
            'payment_terms' => 'NET 12',
            'due_date' => gmmktime(0, 0, 0, 6, 24, 2028),
            'url' => null,
            'payment_url' => null,
            'pdf_url' => null,
            'csv_url' => null,
            'status' => InvoiceStatus::Draft->value,
            'draft' => true,
            'closed' => false,
            'paid' => false,
            'chase' => false,
            'purchase_order' => null,
            'payment_plan' => null,
            'next_chase_on' => null,
            'needs_attention' => null,
            'autopay' => false,
            'payment_source' => null,
            'attempt_count' => null,
            'next_payment_attempt' => null,
            'ship_to' => null,
            'metadata' => new stdClass(),
            'created_at' => null,
            'late_fees' => true,
            'updated_at' => null,
            'network_document_id' => null,
            'subscription_id' => self::$subscription->id(),
        ];

        $this->assertEquals($expected, $this->expandInvoice($invoice));
    }

    public function testBuildSubscriptionCancelAtPeriodEnd(): void
    {
        $subscription = new Subscription();
        $subscription->renews_next = strtotime('+1 month');
        $subscription->cancel_at_period_end = true;

        $upcoming = $this->getUpcoming();

        $invoice = $upcoming->setSubscription($subscription)->build();

        $this->assertInstanceOf(Invoice::class, $invoice);

        $expected = [
            'id' => null,
            'object' => 'invoice',
            'customer' => self::$customer->id(),
            'name' => 'Invoice',
            'currency' => 'usd',
            'items' => [],
            'discounts' => [],
            'shipping' => [],
            'taxes' => [],
            'subtotal' => 0.0,
            'total' => 0.0,
            'balance' => 0.0,
            'notes' => null,
            'number' => null,
            'date' => null,
            'payment_terms' => 'NET 12',
            'due_date' => null,
            'url' => null,
            'payment_url' => null,
            'pdf_url' => null,
            'csv_url' => null,
            'status' => InvoiceStatus::Draft->value,
            'draft' => true,
            'closed' => false,
            'paid' => false,
            'chase' => false,
            'purchase_order' => null,
            'payment_plan' => null,
            'next_chase_on' => null,
            'needs_attention' => null,
            'autopay' => false,
            'payment_source' => null,
            'attempt_count' => null,
            'next_payment_attempt' => null,
            'ship_to' => null,
            'metadata' => new stdClass(),
            'created_at' => null,
            'late_fees' => true,
            'updated_at' => null,
            'network_document_id' => null,
            'subscription_id' => null,
        ];

        $this->assertEquals($expected, $this->expandInvoice($invoice));
    }

    public function testBuildNoSubscriptionNoItems(): void
    {
        $upcoming = $this->getUpcoming();

        $invoice = $upcoming->withoutSubscription()->build();

        $this->assertInstanceOf(Invoice::class, $invoice);

        $expected = [
            'id' => null,
            'object' => 'invoice',
            'customer' => self::$customer->id(),
            'name' => 'Invoice',
            'currency' => 'usd',
            'items' => [],
            'subtotal' => 0.0,
            'total' => 0.0,
            'balance' => 0,
            'discounts' => [],
            'shipping' => [],
            'taxes' => [],
            'notes' => null,
            'number' => null,
            'date' => null,
            'payment_terms' => 'NET 12',
            'due_date' => null,
            'url' => null,
            'payment_url' => null,
            'pdf_url' => null,
            'csv_url' => null,
            'status' => InvoiceStatus::Draft->value,
            'draft' => true,
            'closed' => false,
            'paid' => false,
            'chase' => false,
            'purchase_order' => null,
            'payment_plan' => null,
            'next_chase_on' => null,
            'needs_attention' => null,
            'autopay' => false,
            'payment_source' => null,
            'attempt_count' => null,
            'next_payment_attempt' => null,
            'ship_to' => null,
            'metadata' => new stdClass(),
            'created_at' => null,
            'late_fees' => true,
            'updated_at' => null,
            'network_document_id' => null,
            'subscription_id' => null,
        ];

        $this->assertEquals($expected, $this->expandInvoice($invoice));
    }

    public function testBuildNoSubscriptionWithPendingItems(): void
    {
        // create pending line items
        $line = new PendingLineItem();
        $line->setParent(self::$customer);
        $line->name = 'Line 1';
        $line->unit_cost = 100;
        $this->assertTrue($line->save());

        $upcoming = $this->getUpcoming();

        $invoice = $upcoming->withoutSubscription()->build();

        $this->assertInstanceOf(Invoice::class, $invoice);

        $expected = [
            'id' => null,
            'object' => 'invoice',
            'customer' => self::$customer->id(),
            'name' => 'Invoice',
            'currency' => 'usd',
            'items' => [
                [
                    'catalog_item' => null,
                    'quantity' => 1,
                    'name' => 'Line 1',
                    'description' => null,
                    'unit_cost' => 100.00,
                    'amount' => 100.00,
                    'type' => null,
                    'discountable' => true,
                    'discounts' => [],
                    'taxable' => true,
                    'taxes' => [],
                    'metadata' => new stdClass(),
                ],
            ],
            'discounts' => [],
            'shipping' => [],
            'taxes' => [],
            'subtotal' => 100.0,
            'total' => 100.0,
            'balance' => 0,
            'notes' => null,
            'number' => null,
            'date' => null,
            'payment_terms' => 'NET 12',
            'due_date' => null,
            'url' => null,
            'payment_url' => null,
            'pdf_url' => null,
            'csv_url' => null,
            'status' => InvoiceStatus::Draft->value,
            'draft' => true,
            'closed' => false,
            'paid' => false,
            'chase' => false,
            'purchase_order' => null,
            'payment_plan' => null,
            'next_chase_on' => null,
            'needs_attention' => null,
            'autopay' => false,
            'payment_source' => null,
            'attempt_count' => null,
            'next_payment_attempt' => null,
            'ship_to' => null,
            'metadata' => new stdClass(),
            'created_at' => null,
            'updated_at' => null,
            'late_fees' => true,
            'network_document_id' => null,
            'subscription_id' => null,
        ];

        $this->assertEquals($expected, $this->expandInvoice($invoice));
    }

    public function testBuildSubscriptionWithPending(): void
    {
        $upcoming = $this->getUpcoming();

        $invoice = $upcoming->build();

        $this->assertInstanceOf(Invoice::class, $invoice);

        $expected = [
            'id' => null,
            'object' => 'invoice',
            'customer' => self::$customer->id(),
            'name' => 'Starter',
            'currency' => 'usd',
            'items' => [
                [
                    'type' => 'plan',
                    'plan' => 'starter',
                    'catalog_item' => null,
                    'subscription' => self::$subscription->id(),
                    'period_start' => gmmktime(0, 0, 0, 6, 12, 2028),
                    'period_end' => gmmktime(23, 59, 59, 8, 11, 2028),
                    'prorated' => false,
                    'name' => 'Starter',
                    'description' => '',
                    'quantity' => 1.0,
                    'unit_cost' => 100.0,
                    'amount' => 100.0,
                    'taxes' => [],
                    'discounts' => [],
                    'taxable' => true,
                    'discountable' => true,
                    'metadata' => new stdClass(),
                ],
                [
                    'catalog_item' => null,
                    'quantity' => 1,
                    'name' => 'Line 1',
                    'description' => null,
                    'unit_cost' => 100.00,
                    'amount' => 100.00,
                    'type' => null,
                    'discountable' => true,
                    'discounts' => [],
                    'taxable' => true,
                    'taxes' => [],
                    'metadata' => new stdClass(),
                ],
            ],
            'discounts' => [],
            'shipping' => [],
            'taxes' => [],
            'subtotal' => 200.0,
            'total' => 200.0,
            'balance' => 0,
            'notes' => null,
            'number' => null,
            'date' => gmmktime(0, 0, 0, 6, 12, 2028),
            'payment_terms' => 'NET 12',
            'due_date' => gmmktime(0, 0, 0, 6, 24, 2028),
            'url' => null,
            'payment_url' => null,
            'pdf_url' => null,
            'csv_url' => null,
            'status' => InvoiceStatus::Draft->value,
            'draft' => true,
            'closed' => false,
            'paid' => false,
            'chase' => false,
            'purchase_order' => null,
            'payment_plan' => null,
            'next_chase_on' => null,
            'needs_attention' => null,
            'autopay' => false,
            'payment_source' => null,
            'attempt_count' => null,
            'next_payment_attempt' => null,
            'ship_to' => null,
            'metadata' => new stdClass(),
            'created_at' => null,
            'late_fees' => true,
            'updated_at' => null,
            'network_document_id' => null,
            'subscription_id' => self::$subscription->id(),
        ];

        $this->assertEquals($expected, $this->expandInvoice($invoice));
    }

    public function testBuildCanceledSubscription(): void
    {
        self::getService('test.cancel_subscription')->cancel(self::$subscription);

        $upcoming = $this->getUpcoming();

        $invoice = $upcoming->setSubscription(self::$subscription)->build();

        $this->assertInstanceOf(Invoice::class, $invoice);

        $expected = [
            'id' => null,
            'object' => 'invoice',
            'customer' => self::$customer->id(),
            'name' => 'Invoice',
            'currency' => 'usd',
            'items' => [
                [
                    'catalog_item' => null,
                    'quantity' => 1,
                    'name' => 'Line 1',
                    'description' => null,
                    'unit_cost' => 100.00,
                    'amount' => 100.00,
                    'type' => null,
                    'discountable' => true,
                    'discounts' => [],
                    'taxable' => true,
                    'taxes' => [],
                    'metadata' => new stdClass(),
                ],
            ],
            'discounts' => [],
            'shipping' => [],
            'taxes' => [],
            'subtotal' => 100.0,
            'total' => 100.0,
            'balance' => 0,
            'notes' => null,
            'number' => null,
            'date' => null,
            'payment_terms' => 'NET 12',
            'due_date' => null,
            'url' => null,
            'payment_url' => null,
            'pdf_url' => null,
            'csv_url' => null,
            'status' => InvoiceStatus::Draft->value,
            'draft' => true,
            'closed' => false,
            'paid' => false,
            'chase' => false,
            'purchase_order' => null,
            'payment_plan' => null,
            'next_chase_on' => null,
            'needs_attention' => null,
            'autopay' => false,
            'payment_source' => null,
            'attempt_count' => null,
            'next_payment_attempt' => null,
            'ship_to' => null,
            'metadata' => new stdClass(),
            'created_at' => null,
            'late_fees' => true,
            'updated_at' => null,
            'network_document_id' => null,
            'subscription_id' => null,
        ];

        $this->assertEquals($expected, $this->expandInvoice($invoice));
    }

    public function testBuildCancelAtPeriodEndSubscription(): void
    {
        $subscription = self::getService('test.create_subscription')
            ->create([
                'customer' => self::$customer,
                'plan' => self::$plan,
                'start_date' => strtotime('+1 month'),
            ]);

        $subscription->contract_period_end = (int) mktime(0, 0, 0, (int) date('m') + 2, (int) date('d'), (int) date('Y'));
        $subscription->renews_next = $subscription->contract_period_end;
        $subscription->cancel_at_period_end = true;

        $upcoming = $this->getUpcoming();

        $invoice = $upcoming->setSubscription($subscription)->build();

        $this->assertInstanceOf(Invoice::class, $invoice);

        $expected = [
            'id' => null,
            'object' => 'invoice',
            'customer' => self::$customer->id(),
            'name' => 'Invoice',
            'currency' => 'usd',
            'items' => [
                [
                    'catalog_item' => null,
                    'quantity' => 1,
                    'name' => 'Line 1',
                    'description' => null,
                    'unit_cost' => 100.00,
                    'amount' => 100.00,
                    'type' => null,
                    'discountable' => true,
                    'discounts' => [],
                    'taxable' => true,
                    'taxes' => [],
                    'metadata' => new stdClass(),
                ],
            ],
            'discounts' => [],
            'shipping' => [],
            'taxes' => [],
            'subtotal' => 100.0,
            'total' => 100.0,
            'balance' => 0,
            'notes' => null,
            'number' => null,
            'date' => null,
            'payment_terms' => 'NET 12',
            'due_date' => null,
            'url' => null,
            'payment_url' => null,
            'pdf_url' => null,
            'csv_url' => null,
            'status' => InvoiceStatus::Draft->value,
            'draft' => true,
            'closed' => false,
            'paid' => false,
            'chase' => false,
            'purchase_order' => null,
            'payment_plan' => null,
            'next_chase_on' => null,
            'needs_attention' => null,
            'autopay' => false,
            'payment_source' => null,
            'attempt_count' => null,
            'next_payment_attempt' => null,
            'ship_to' => null,
            'metadata' => new stdClass(),
            'created_at' => null,
            'late_fees' => true,
            'updated_at' => null,
            'network_document_id' => null,
            'subscription_id' => null,
        ];

        $this->assertEquals($expected, $this->expandInvoice($invoice));
    }

    public function testBuildPendingWithCustomerCurrency(): void
    {
        self::$company->features->enable('multi_currency');

        self::$customer->currency = 'eur';
        self::$customer->saveOrFail();

        $upcoming = $this->getUpcoming();

        $invoice = $upcoming->withoutSubscription()->build();

        $this->assertInstanceOf(Invoice::class, $invoice);

        $expected = [
            'id' => null,
            'object' => 'invoice',
            'customer' => self::$customer->id(),
            'name' => 'Invoice',
            'currency' => 'eur',
            'items' => [
                [
                    'catalog_item' => null,
                    'quantity' => 1,
                    'name' => 'Line 1',
                    'description' => null,
                    'unit_cost' => 100.00,
                    'amount' => 100.00,
                    'type' => null,
                    'discountable' => true,
                    'discounts' => [],
                    'taxable' => true,
                    'taxes' => [],
                    'metadata' => new stdClass(),
                ],
            ],
            'discounts' => [],
            'shipping' => [],
            'taxes' => [],
            'subtotal' => 100.0,
            'total' => 100.0,
            'balance' => 0,
            'notes' => null,
            'number' => null,
            'date' => null,
            'payment_terms' => 'NET 12',
            'due_date' => null,
            'url' => null,
            'payment_url' => null,
            'pdf_url' => null,
            'csv_url' => null,
            'status' => InvoiceStatus::Draft->value,
            'draft' => true,
            'closed' => false,
            'paid' => false,
            'chase' => false,
            'purchase_order' => null,
            'payment_plan' => null,
            'next_chase_on' => null,
            'needs_attention' => null,
            'autopay' => false,
            'payment_source' => null,
            'attempt_count' => null,
            'next_payment_attempt' => null,
            'ship_to' => null,
            'metadata' => new stdClass(),
            'created_at' => null,
            'late_fees' => true,
            'updated_at' => null,
            'network_document_id' => null,
            'subscription_id' => null,
        ];

        $this->assertEquals($expected, $this->expandInvoice($invoice));
    }

    private function getUpcoming(): UpcomingInvoice
    {
        return new UpcomingInvoice(self::$customer);
    }

    private function expandInvoice(Invoice $invoice): array
    {
        $arr = $invoice->toArray();

        // remove item ids
        foreach ($arr['items'] as &$item) {
            unset($item['id']);
            unset($item['created_at']);
            unset($item['updated_at']);
            unset($item['object']);
            foreach (['discounts', 'taxes'] as $type) {
                foreach ($item[$type] as &$rate) {
                    unset($rate['id']);
                    unset($rate['object']);
                }
            }
        }

        // remove applied rate ids
        foreach (['discounts', 'taxes', 'shipping'] as $type) {
            foreach ($arr[$type] as &$rate) {
                unset($rate['id']);
                unset($rate['object']);
            }
        }

        return $arr;
    }
}
