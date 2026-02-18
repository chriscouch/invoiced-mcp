<?php

namespace App\Tests\Core\Billing\BillingSystem;

use App\Core\Billing\BillingSystem\InvoicedBillingSystem;
use App\Core\Billing\Enums\BillingInterval;
use App\Core\Billing\Enums\UsageType;
use App\Core\Billing\Models\BillingProfile;
use App\Core\Billing\ValueObjects\BillingOneTimeItem;
use App\Core\Billing\ValueObjects\BillingSubscriptionItem;
use App\Core\Entitlements\Models\Product;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Utils\RandomString;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Carbon\CarbonTimeZone;
use Invoiced\Client;
use Invoiced\Customer;
use Invoiced\Invoice;
use Invoiced\Item;
use Invoiced\LineItem;
use Invoiced\Plan;
use Invoiced\Subscription;
use Mockery;

class InvoicedBillingSystemTest extends AppTestCase
{
    private static BillingProfile $billingProfile;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::$billingProfile = BillingProfile::getOrCreate(self::$company);
        self::$billingProfile->billing_system = 'invoiced';
        self::$billingProfile->invoiced_customer = RandomString::generate();
        self::$billingProfile->billing_interval = BillingInterval::Monthly;
        self::$billingProfile->saveOrFail();
    }

    public function testCreateOrUpdateCustomer(): void
    {
        $mockClient = Mockery::mock(Client::class);

        $mockCustomer = Mockery::mock(Customer::class, [$mockClient, RandomString::generate()]);
        $mockCustomer->shouldReceive('create')
            ->withArgs([
                [
                    'name' => 'Test',
                    'email' => 'test@example.com',
                    'attention_to' => 'Bob Loblaw',
                    'address1' => '1234 main st',
                    'city' => 'Austin',
                    'state' => 'TX',
                    'postal_code' => '78701',
                    'country' => 'US',
                    'autopay' => true,
                    'metadata' => [],
                ],
            ])
            ->once()
            ->andReturn($mockCustomer);

        $mockClient->Customer = $mockCustomer;

        $billingProfile = new BillingProfile([
            'billing_system' => null,
            'billing_interval' => BillingInterval::Monthly,
        ]);
        $params = [
            'company' => 'Test',
            'person' => 'Bob Loblaw',
            'email' => 'test@example.com',
            'address1' => '1234 main st',
            'address2' => null,
            'city' => 'Austin',
            'state' => 'TX',
            'postal_code' => '78701',
            'country' => 'US',
            'autopay' => true,
        ];

        $billingSystem = $this->getBillingSystem($mockClient);

        $billingSystem->createOrUpdateCustomer($billingProfile, $params);

        $this->assertEquals('invoiced', $billingProfile->billing_system);
        $this->assertEquals($mockCustomer->id, $billingProfile->invoiced_customer);
    }

    public function testCreateSubscription(): void
    {
        $mockClient = Mockery::mock(Client::class);

        $mockCustomer = Mockery::mock(Customer::class, [$mockClient, RandomString::generate()]);
        $mockCustomer->shouldReceive('retrieve')->andReturn($mockCustomer)->once();

        $product = Product::where('name', 'Advanced Accounts Receivable')->one();
        $startDate = new CarbonImmutable('2023-08-24T00:00:00Z');

        $mockSubscription = Mockery::mock(Subscription::class, [$mockClient, 1234]);
        $mockSubscription->status = 'active';
        $mockSubscription->plan = 'invoiced-ar-pay-as-you-go';
        $mockSubscription->shouldReceive('create')
            ->withArgs([
                [
                    'customer' => $mockCustomer->id,
                    'start_date' => 1692835200,
                    'plan' => 'invoiced-product-'.$product->id.'-monthly',
                    'quantity' => 1,
                    'amount' => 1000,
                    'description' => null,
                    'addons' => [],
                ],
            ])
            ->once()
            ->andReturn([[$mockSubscription]]);

        $mockClient->Customer = $mockCustomer;
        $mockClient->Subscription = $mockSubscription;

        $billingProfile = new BillingProfile([
            'billing_system' => null,
            'billing_interval' => BillingInterval::Monthly,
            'invoiced_customer' => $mockCustomer->id,
        ]);

        $billingSystem = $this->getBillingSystem($mockClient);

        $plan = new Plan($mockClient, 'invoiced-product-'.$product->id.'-monthly', [
            'interval' => 'month',
            'interval_count' => 1,
        ]);
        $billingSystem->setPlan($plan);

        $item = new Item($mockClient, 'invoiced-product-'.$product->id, []);
        $billingSystem->setItem($item);

        $subscriptionItems = [
            new BillingSubscriptionItem(
                price: new Money('usd', 100000),
                billingInterval: BillingInterval::Monthly,
                product: $product,
            ),
        ];

        $billingSystem->createSubscription($billingProfile, $subscriptionItems, $startDate);
    }

    public function testUpdateSubscription(): void
    {
        $client = Mockery::mock(Client::class);

        $customerResult = Mockery::mock(Customer::class, [$client, 123]);
        $mockCustomer = Mockery::mock();
        $mockCustomer->shouldReceive('retrieve')->andReturn($customerResult);
        $client->Customer = $mockCustomer;

        $subscriptionResult = Mockery::mock(Subscription::class, [$client, 123]);
        $subscriptionResult->customer = 123;
        $subscriptionResult->status = 'active';
        $subscriptionResult->plan = 'whatever';
        $subscriptionResult->quantity = 1;
        $subscriptionResult->amount = null;
        $subscriptionResult->addons = [
            [
                'id' => 'eh',
                'plan' => 'sure',
                'amount' => null,
                'quantity' => 1,
            ],
        ];
        $subscriptionResult->shouldReceive('save')
            ->andReturn(true);

        $mockSubscription = Mockery::mock(Subscription::class);
        $mockSubscription->shouldReceive('all')
            ->andReturn([[$subscriptionResult]]);
        $client->Subscription = $mockSubscription;

        $billingSystem = $this->getBillingSystem($client);

        $product = Product::where('name', 'Advanced Accounts Receivable')->one();
        $plan = new Plan($client, 'invoiced-product-'.$product->id.'-monthly', [
            'interval' => 'month',
            'interval_count' => 1,
        ]);
        $billingSystem->setPlan($plan);

        $item = new Item($client, 'invoiced-product-'.$product->id, []);
        $billingSystem->setItem($item);

        $subscriptionItems = [
            new BillingSubscriptionItem(
                price: new Money('usd', 100000),
                billingInterval: BillingInterval::Monthly,
                product: $product,
            ),
        ];
        $prorationDate = CarbonImmutable::now();
        $billingSystem->updateSubscription(self::$billingProfile, $subscriptionItems, true, $prorationDate);

        $this->assertTrue($subscriptionResult->prorate); /* @phpstan-ignore-line */
        $this->assertEquals(1, $subscriptionResult->quantity);
        $this->assertEquals('invoiced-product-'.$product->id.'-monthly', $subscriptionResult->plan);
        $this->assertEquals(1000.0, $subscriptionResult->amount);
        $this->assertCount(0, $subscriptionResult->addons);
        $this->assertEquals($prorationDate->getTimestamp(), $subscriptionResult->proration_date) /* @phpstan-ignore-line */;
    }

    public function testSetDefaultPaymentMethod(): void
    {
        $client = Mockery::mock(Client::class);

        $customerResult = Mockery::mock(Customer::class, [$client, 123]);
        $customerResult->shouldReceive('save')->once();
        $mockCustomer = Mockery::mock();
        $mockCustomer->shouldReceive('retrieve')->andReturn($customerResult);
        $client->Customer = $mockCustomer;

        $billingSystem = $this->getBillingSystem($client);

        $billingSystem->setDefaultPaymentMethod(self::$billingProfile, 'tok_test');

        $this->assertEquals((object) [
            'method' => 'credit_card',
            'invoiced_token' => 'tok_test',
        ], $customerResult->payment_source);
    }

    public function testBillLineItem(): void
    {
        $mockClient = Mockery::mock(Client::class);

        $mockLineItem = Mockery::mock(LineItem::class, [$mockClient, 456]);
        $mockLineItem->shouldReceive('create')
            ->withArgs([[
                'name' => 'Invoices/Month',
                'description' => 'TEST usage for October 2015',
                'quantity' => 11,
                'unit_cost' => 1,
                'plan' => 'invoiced-usage-invoice-monthly',
                'catalog_item' => null,
                'period_start' => 1443675600,
                'period_end' => 1446353999,
            ]])
            ->andReturn($mockLineItem);

        $mockCustomer = Mockery::mock(Customer::class, [$mockClient, 123]);
        $mockCustomer->shouldReceive('retrieve')->once()->andReturn($mockCustomer);
        $mockCustomer->shouldReceive('lineItems')->andReturn($mockLineItem);
        $mockCustomer->shouldReceive('invoice');
        $mockClient->Customer = $mockCustomer;

        $billingSystem = $this->getBillingSystem($mockClient);

        $plan = new Plan($mockClient, 'invoiced-usage-invoice-monthly');
        $billingSystem->setPlan($plan);

        $item = new Item($mockClient, 'invoiced-usage-invoice', []);
        $billingSystem->setItem($item);

        $charge = new BillingOneTimeItem(
            price: new Money('usd', 100),
            quantity: 11,
            description: 'TEST usage for October 2015',
            usageType: UsageType::InvoicesPerMonth,
            billingInterval: BillingInterval::Monthly,
            periodStart: new CarbonImmutable('2015-10-01T00:00:00', new CarbonTimeZone('America/Chicago')),
            periodEnd: new CarbonImmutable('2015-10-31T23:59:59', new CarbonTimeZone('America/Chicago')),
        );

        $this->assertEquals('456', $billingSystem->billLineItem(self::$billingProfile, $charge, true));
    }

    public function testCancel(): void
    {
        $mockClient = Mockery::mock(Client::class);
        $mockCustomer = Mockery::mock(Customer::class, [$mockClient, 123]);
        $mockCustomer->shouldReceive('retrieve')->once()->andReturn($mockCustomer);
        $mockCustomer->shouldReceive('invoice')->once();

        $mockSubscription = Mockery::mock(Subscription::class, [$mockClient, 1234]);
        $mockSubscription->status = 'active';
        $mockSubscription->shouldReceive('all')->once()->andReturn([[$mockSubscription]]);
        $mockSubscription->shouldReceive('delete')->once();

        $mockClient->Customer = $mockCustomer;
        $mockClient->Subscription = $mockSubscription;

        $billingSystem = $this->getBillingSystem($mockClient);

        $billingSystem->cancel($this->getBillingProfile(), false);
    }

    public function testCancelAtPeriodEnd(): void
    {
        $mockClient = Mockery::mock(Client::class);

        $mockCustomer = Mockery::mock(Customer::class, [$mockClient, 123]);
        $mockCustomer->shouldReceive('retrieve')->once()->andReturn($mockCustomer);
        $mockCustomer->shouldReceive('invoice');

        $mockSubscription = Mockery::mock(Subscription::class, [$mockClient, 1234]);
        $mockSubscription->status = 'active';
        $mockSubscription->shouldReceive('all')->once()->andReturn([[$mockSubscription]]);
        $mockSubscription->shouldReceive('save');

        $mockClient->Customer = $mockCustomer;
        $mockClient->Subscription = $mockSubscription;

        $billingSystem = $this->getBillingSystem($mockClient);

        $billingSystem->cancel($this->getBillingProfile(), true);
    }

    public function testReactivate(): void
    {
        $mockClient = Mockery::mock(Client::class);

        $mockSubscription = Mockery::mock(Subscription::class, [$mockClient, 1234]);
        $mockSubscription->status = 'active';
        $mockSubscription->shouldReceive('all')->once()->andReturn([[$mockSubscription]]);
        $mockSubscription->shouldReceive('save')->once();

        $mockClient->Subscription = $mockSubscription;

        $billingSystem = $this->getBillingSystem($mockClient);

        $billingSystem->reactivate($this->getBillingProfile());

        $this->assertFalse($mockSubscription->cancel_at_period_end);
    }

    public function testGetBillingState(): void
    {
        $client = Mockery::mock(Client::class);

        $customerResult = Mockery::mock(Customer::class, [$client, 123]);
        $customerResult->autopay = true;
        $customerResult->payment_source = [/* @phpstan-ignore-line */
            'object' => 'card',
            'last4' => '1234',
            'exp_month' => 4,
            'exp_year' => 2021,
            'brand' => 'American Express',
        ];
        $customerResult->shouldReceive('getEndpoint')->andReturn('/customers/123');
        $mockCustomer = Mockery::mock();
        $mockCustomer->shouldReceive('retrieve')->andReturn($customerResult);
        $client->Customer = $mockCustomer;

        $subscriptionResult = Mockery::mock(Subscription::class, [$client, 123]);
        $subscriptionResult->status = 'active';
        $subscriptionResult->cancel_at_period_end = false;
        $subscriptionResult->period_end = (int) mktime(0, 0, 0, 4, 20, 2044);

        $mockSubscription = Mockery::mock();
        $mockSubscription->shouldReceive('all')
            ->andReturn([[$subscriptionResult], null]);
        $client->Subscription = $mockSubscription;

        $client->shouldReceive('request')
            ->withArgs([
                'get',
                '/customers/123/upcoming_invoice',
            ])
            ->andReturn([
                'body' => [
                    'total' => 500.0,
                ],
            ]);

        $billingSystem = $this->getBillingSystem($client);

        $billingProfile = $this->getBillingProfile();

        $billingState = $billingSystem->getBillingState($billingProfile);
        $this->assertEquals([
            'last4' => '1234',
            'exp_month' => 4,
            'exp_year' => 2021,
            'type' => 'American Express',
            'object' => 'card',
        ], $billingState->paymentSource);
        $this->assertNull($billingState->discount);
        $this->assertEquals(new CarbonImmutable('2044-04-20T00:00:00Z'), $billingState->nextBillDate);
        $this->assertFalse($billingState->cancelAtPeriodEnd);
        $this->assertEquals(500.0, $billingState->nextChargeAmount);
        $this->assertTrue($billingState->autopay);
    }

    public function testGetBillingHistory(): void
    {
        $client = Mockery::mock(Client::class);

        $customerResult = Mockery::mock(Customer::class, [$client, 123]);
        $mockCustomer = Mockery::mock();
        $mockCustomer->shouldReceive('retrieve')->andReturn($customerResult);
        $client->Customer = $mockCustomer;

        $invoices = [
            new Invoice($client, '123', [
                'status' => 'paid',
                'date' => mktime(0, 0, 0, 4, 20, 2021),
                'total' => 500.0,
                'pdf_url' => 'https://example.com/invoice_pdf',
                'payment_url' => 'https://example.com/pay',
            ]),
        ];

        $mockInvoice = Mockery::mock();
        $mockInvoice->shouldReceive('all')
            ->once()
            ->andReturn([$invoices, null]);
        $client->Invoice = $mockInvoice;

        $billingSystem = $this->getBillingSystem($client);

        $billingProfile = $this->getBillingProfile();

        $expected = [
            [
                'date' => mktime(0, 0, 0, 4, 20, 2021),
                'amount' => 500.0,
                'invoice_url' => 'https://example.com/invoice_pdf',
                'payment_url' => 'https://example.com/pay',
                'error' => null,
            ],
        ];

        $this->assertEquals($expected, $billingSystem->getBillingHistory($billingProfile));
    }

    public function testUpdatePaymentInfoUrl(): void
    {
        $client = Mockery::mock(Client::class);

        $customerResult = Mockery::mock(Customer::class, [$client, 123]);
        $customerResult->statement_pdf_url = 'https://invoiced.com/statements/mjpl0dvplvveqvmzqs5f74l9/u1IHu9iJgtVwT300jlQORJUC/pdf';
        $mockCustomer = Mockery::mock();
        $mockCustomer->shouldReceive('retrieve')->andReturn($customerResult);
        $client->Customer = $mockCustomer;

        $billingProfile = $this->getBillingProfile();
        $this->assertEquals('https://invoicedinc.invoiced.com/paymentInfo/u1IHu9iJgtVwT300jlQORJUC', $this->getBillingSystem($client)->getUpdatePaymentInfoUrl($billingProfile));
    }

    private function getBillingSystem(Client $mockClient = null): InvoicedBillingSystem
    {
        $billingSystem = new InvoicedBillingSystem('invd_test', 'test');
        $billingSystem->setLogger(self::$logger);
        $mockClient = $mockClient ?? Mockery::mock(Client::class);
        $billingSystem->setClient($mockClient);

        return $billingSystem;
    }

    private function getBillingProfile(): BillingProfile
    {
        static $counter = 1;

        return new BillingProfile([
            'id' => ++$counter,
            'billing_system' => 'invoiced',
            'invoiced_customer' => RandomString::generate(),
            'billing_interval' => BillingInterval::Monthly,
        ]);
    }
}
