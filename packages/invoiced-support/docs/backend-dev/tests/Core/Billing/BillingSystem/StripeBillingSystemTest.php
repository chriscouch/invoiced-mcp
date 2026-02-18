<?php

namespace App\Tests\Core\Billing\BillingSystem;

use App\Companies\Models\Company;
use App\Core\Billing\BillingSystem\StripeBillingSystem;
use App\Core\Billing\Enums\BillingInterval;
use App\Core\Billing\Enums\UsageType;
use App\Core\Billing\Exception\BillingException;
use App\Core\Billing\Models\BillingProfile;
use App\Core\Billing\ValueObjects\BillingOneTimeItem;
use App\Core\Billing\ValueObjects\BillingSubscriptionItem;
use App\Core\Entitlements\Models\Product;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Utils\RandomString;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Carbon\CarbonTimeZone;
use Mockery;
use stdClass;
use Stripe\Customer;
use Stripe\Exception\InvalidRequestException as StripeError;
use Stripe\Price as StripePrice;
use Stripe\Product as StripeProduct;
use Stripe\StripeClient;
use Stripe\Subscription;

class StripeBillingSystemTest extends AppTestCase
{
    private static BillingProfile $billingProfile;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();

        self::$billingProfile = BillingProfile::getOrCreate(self::$company);
        self::$billingProfile->billing_system = 'stripe';
        self::$billingProfile->stripe_customer = RandomString::generate();
        self::$billingProfile->billing_interval = BillingInterval::Monthly;
        self::$billingProfile->saveOrFail();
    }

    public function testGetStripeCustomerData(): void
    {
        $params = [
            'company' => 'Invoiced, Inc.',
            'email' => 'hello@invoiced.com',
            'address1' => '4301 W William Cannon Dr',
            'address2' => 'Ste B-150-118',
            'city' => 'Austin',
            'state' => 'TX',
            'postal_code' => '78749',
            'country' => 'US',
        ];

        $expected = [
            'description' => 'Invoiced, Inc.',
            'email' => 'hello@invoiced.com',
            'metadata' => [
                'referred_by' => null,
                'Address_Line1' => '4301 W William Cannon Dr',
                'Address_Line2' => 'Ste B-150-118',
                'Address_City' => 'Austin',
                'Address_State' => 'TX',
                'Address_PostalCode' => '78749',
                'Address_Country' => 'US',
                'TaxCode' => 'SW052000',
                'ItemCode' => 'invoiced-subscription',
            ],
        ];

        $this->assertEquals($expected, $this->getBillingSystem()->stripeCustomerData(self::$billingProfile, $params));
    }

    public function testGetStripeCustomerRetrieve(): void
    {
        $billingSystem = $this->getBillingSystem();

        $stripe = Mockery::mock(StripeClient::class);
        $customer = $this->setUpCustomerMock($stripe);
        $billingSystem->setStripe($stripe);
        $billingProfile = new BillingProfile(['stripe_customer' => $customer->id]);

        $customer = $billingSystem->getCustomer($billingProfile);
        $this->assertEquals('Test', $customer->name);
    }

    public function testGetStripeCustomerRetrieveFail(): void
    {
        $this->expectException(BillingException::class);

        $billingSystem = $this->getBillingSystem();

        $billingProfile = new BillingProfile(['stripe_customer' => RandomString::generate()]);

        $e = new StripeError('error');
        $staticCustomer = Mockery::mock('Stripe\Customer');
        $staticCustomer->shouldReceive('retrieve')
            ->withArgs([$billingProfile->stripe_customer])
            ->andThrow($e)
            ->once();
        $stripe = Mockery::mock(StripeClient::class);
        $stripe->customers = $staticCustomer;
        $billingSystem->setStripe($stripe);

        $billingSystem->getCustomer($billingProfile);
    }

    public function testCreateSubscription(): void
    {
        $billingSystem = $this->getBillingSystem();
        $stripe = Mockery::mock(StripeClient::class);
        $customer = $this->setUpCustomerMock($stripe);

        $renewalTime = 2345678901;

        $resultSub = new stdClass();
        $resultSub->status = 'active';
        $resultSub->current_period_end = $renewalTime;
        $resultSub->trial_end = 100;

        $product = Product::where('name', 'Advanced Accounts Receivable')->one();

        $stripeSubscription = Mockery::mock('Stripe\Subscription');
        $stripeSubscription->shouldReceive('create')
            ->withArgs([[
                'customer' => $customer->id,
                'description' => 'Invoiced Subscription',
                'items' => [
                    [
                        'quantity' => 1,
                        'price' => 'price_1234',
                    ],
                ],
                'pay_immediately' => false,
                'trial_end' => 'now',
            ]])
            ->andReturn($resultSub)
            ->once();
        $stripe->subscriptions = $stripeSubscription;
        $billingSystem->setStripe($stripe);

        $stripeProduct = new StripeProduct('invoiced-product-'.$product->id);
        $billingSystem->setProduct($stripeProduct);
        $stripePrice = new StripePrice('price_1234');
        $billingSystem->setPrice($stripePrice, 'invoiced-product-'.$product->id.'-monthly-100000');

        $subscriptionItems = [
            new BillingSubscriptionItem(
                price: new Money('usd', 100000),
                billingInterval: BillingInterval::Monthly,
                product: $product,
            ),
        ];

        $billingSystem->createSubscription(self::$billingProfile, $subscriptionItems, CarbonImmutable::now());
    }

    public function testCreateFail(): void
    {
        $this->expectException(BillingException::class);

        $billingSystem = $this->getBillingSystem();
        $stripe = Mockery::mock(StripeClient::class);
        $customer = $this->setUpCustomerMock($stripe);

        $product = Product::where('name', 'Advanced Accounts Receivable')->one();

        $stripeSubscription = Mockery::mock('Stripe\Subscription');
        $stripeSubscription->shouldReceive('create')
            ->withArgs([[
                'customer' => $customer->id,
                'description' => 'Invoiced Subscription',
                'items' => [
                    [
                        'quantity' => 1,
                        'price' => 'price_1234',
                    ],
                ],
                'pay_immediately' => false,
                'trial_end' => 'now',
            ]])
            ->andThrow(new StripeError('error'))
            ->once();
        $stripe->subscriptions = $stripeSubscription;
        $billingSystem->setStripe($stripe);

        $stripeProduct = new StripeProduct('invoiced-product-'.$product->id);
        $billingSystem->setProduct($stripeProduct);
        $stripePrice = new StripePrice('price_1234');
        $billingSystem->setPrice($stripePrice, 'invoiced-product-'.$product->id.'-monthly-100000');

        $subscriptionItems = [
            new BillingSubscriptionItem(
                price: new Money('usd', 100000),
                billingInterval: BillingInterval::Monthly,
                product: $product,
            ),
        ];

        $billingSystem->createSubscription(self::$billingProfile, $subscriptionItems, CarbonImmutable::now());
    }

    public function testUpdateSubscription(): void
    {
        $billingSystem = $this->getBillingSystem();
        // set up mocks
        $stripe = Mockery::mock(StripeClient::class);
        $customer = $this->setUpCustomerMock($stripe);
        $subscription = $this->setUpSubscriptionMock($customer->id, $stripe);
        $subscription->shouldReceive('save')->once(); /* @phpstan-ignore-line */
        $billingSystem->setStripe($stripe);

        $product = Product::where('name', 'Advanced Accounts Receivable')->one();
        $stripeProduct = new StripeProduct('invoiced-product-'.$product->id);
        $billingSystem->setProduct($stripeProduct);
        $stripePrice = new StripePrice('price_1234');
        $billingSystem->setPrice($stripePrice, 'invoiced-product-'.$product->id.'-monthly-100000');

        $subscriptionItems = [
            new BillingSubscriptionItem(
                price: new Money('usd', 100000),
                billingInterval: BillingInterval::Monthly,
                product: $product,
            ),
        ];
        $prorationDate = CarbonImmutable::now();
        $billingSystem->updateSubscription(self::$billingProfile, $subscriptionItems, true, $prorationDate);

        // Validate parameters used
        $expected = [
            [
                'id' => 'sure',
                'deleted' => true,
            ],
            [
                'price' => 'price_1234',
                'quantity' => 1,
            ],
        ];
        $this->assertEquals($expected, $subscription->items);
        $this->assertEquals('create_prorations', $subscription->proration_behavior);
    }

    public function testSetDefaultPaymentMethod(): void
    {
        $billingSystem = $this->getBillingSystem();

        $company = new Company(['id' => 1]);
        $company->billing_profile = new BillingProfile(['stripe_customer' => 'test']);

        $customer = Mockery::mock();
        $customer->id = 'test';
        $customer->source = null;
        $customer->shouldReceive('save')->once();

        $staticCustomer = Mockery::mock('Stripe\Customer');
        $staticCustomer->shouldReceive('retrieve')
            ->andReturn($customer)
            ->once();
        $stripe = Mockery::mock(StripeClient::class);
        $stripe->customers = $staticCustomer;
        $billingSystem->setStripe($stripe);

        $billingSystem->setDefaultPaymentMethod($company->billing_profile, 'tok_test');

        $this->assertEquals('tok_test', $customer->source);
    }

    public function testSetDefaultPaymentMethodFail(): void
    {
        $this->expectException(BillingException::class);

        $billingSystem = $this->getBillingSystem();

        $company = new Company(['id' => 1]);
        $company->billing_profile = new BillingProfile(['stripe_customer' => 'test']);

        $e = new StripeError('error');
        $customer = Mockery::mock();
        $customer->id = 'test';
        $customer->source = false;
        $customer->shouldReceive('save')
            ->andThrow($e)
            ->once();

        $staticCustomer = Mockery::mock('Stripe\Customer');
        $staticCustomer->shouldReceive('retrieve')
            ->andReturn($customer)
            ->once();
        $stripe = Mockery::mock(StripeClient::class);
        $stripe->customers = $staticCustomer;
        $billingSystem->setStripe($stripe);

        $billingSystem->setDefaultPaymentMethod($company->billing_profile, 'tok_test');
    }

    public function testCancel(): void
    {
        $resultSub = new stdClass();
        $resultSub->status = 'canceled';

        $billingSystem = $this->getBillingSystem();
        $stripe = Mockery::mock(StripeClient::class);
        $customer = $this->setUpCustomerMock($stripe);
        $subscription = $this->setUpSubscriptionMock($customer->id, $stripe);
        $subscription->shouldReceive('cancel')->once();

        $resultItems = new stdClass();
        $resultItems->data = [];

        $invoiceItem = Mockery::mock('Stripe\InvoiceItem');
        $invoiceItem->shouldReceive('all')
            ->withArgs([['customer' => $customer->id, 'pending' => true]])
            ->andReturn($resultItems)
            ->once();
        $stripe->invoiceItems = $invoiceItem;
        $billingSystem->setStripe($stripe);

        $billingSystem->cancel(self::$billingProfile, false);
    }

    public function testCancelAtPeriodEnd(): void
    {
        $resultSub = new stdClass();
        $resultSub->status = 'active';
        $resultSub->cancel_at_period_end = true;
        $resultSub->canceled_at = time();

        $billingSystem = $this->getBillingSystem();
        $stripe = Mockery::mock(StripeClient::class);
        $customer = $this->setUpCustomerMock($stripe);
        $subscription = $this->setUpSubscriptionMock($customer->id, $stripe);
        $subscription->shouldReceive('save')->once();

        $resultItems = new stdClass();
        $resultItems->data = [];

        $invoiceItem = Mockery::mock('Stripe\InvoiceItem');
        $invoiceItem->shouldReceive('all')
            ->withArgs([['customer' => $customer->id, 'pending' => true]])
            ->andReturn($resultItems)
            ->once();
        $stripe->invoiceItems = $invoiceItem;
        $billingSystem->setStripe($stripe);

        $billingSystem->cancel(self::$billingProfile, true);

        // Validate parameters used
        $this->assertTrue($subscription->cancel_at_period_end);
    }

    public function testCancelFail(): void
    {
        $this->expectException(BillingException::class);

        $billingSystem = $this->getBillingSystem();
        $stripe = Mockery::mock(StripeClient::class);
        $customer = $this->setUpCustomerMock($stripe);
        $subscription = $this->setUpSubscriptionMock($customer->id, $stripe);
        $subscription->shouldReceive('cancel')
            ->andThrow(new StripeError('error'));
        $billingSystem->setStripe($stripe);

        $billingSystem->cancel(self::$billingProfile, false);
    }

    public function testReactivate(): void
    {
        $resultSub = new stdClass();
        $resultSub->status = 'active';
        $resultSub->cancel_at_period_end = false;

        $billingSystem = $this->getBillingSystem();

        $stripe = Mockery::mock(StripeClient::class);
        $customer = $this->setUpCustomerMock($stripe);
        $subscription = $this->setUpSubscriptionMock($customer->id, $stripe);
        $subscription->cancel_at_period_end = true;
        $subscription->shouldReceive('save')->once();
        $billingSystem->setStripe($stripe);

        $billingSystem->reactivate(self::$billingProfile);

        // Validate parameters used
        $this->assertFalse($subscription->cancel_at_period_end);
    }

    public function testReactivateFail(): void
    {
        $this->expectException(BillingException::class);

        $billingSystem = $this->getBillingSystem();

        $stripe = Mockery::mock(StripeClient::class);
        $customer = $this->setUpCustomerMock($stripe);
        $subscription = $this->setUpSubscriptionMock($customer->id, $stripe);
        $subscription->shouldReceive('save')
            ->andThrow(new StripeError('error'));
        $billingSystem->setStripe($stripe);

        $billingSystem->reactivate(self::$billingProfile);
    }

    public function testBillLineItem(): void
    {
        $billingSystem = $this->getBillingSystem();

        // set up mocks
        $stripe = Mockery::mock(StripeClient::class);
        $customer = $this->setUpCustomerMock($stripe);
        $stripeItem = Mockery::mock('Stripe\InvoiceItem');
        $stripeItem->shouldReceive('create')
            ->withArgs([[
                'customer' => $customer->id,
                'currency' => 'usd',
                'unit_amount' => 100,
                'quantity' => 11,
                'description' => 'Invoices/Month - TEST usage for October 2015',
                'period' => [
                    'start' => 1443675600,
                    'end' => 1446353999,
                ],
            ]])
            ->once()
            ->andReturn((object) ['id' => 'ii_456']);
        $stripe->invoiceItems = $stripeItem;
        $billingSystem->setStripe($stripe);

        $charge = new BillingOneTimeItem(
            price: new Money('usd', 100),
            quantity: 11,
            description: 'TEST usage for October 2015',
            usageType: UsageType::InvoicesPerMonth,
            billingInterval: BillingInterval::Monthly,
            periodStart: new CarbonImmutable('2015-10-01T00:00:00', new CarbonTimeZone('America/Chicago')),
            periodEnd: new CarbonImmutable('2015-10-31T23:59:59', new CarbonTimeZone('America/Chicago')),
        );
        $this->assertEquals('ii_456', $billingSystem->billLineItem(self::$billingProfile, $charge, false));
    }

    public function testBillOverageLineFail(): void
    {
        $this->expectException(BillingException::class);

        $billingSystem = $this->getBillingSystem();

        // set up mocks
        $stripe = Mockery::mock(StripeClient::class);
        $customer = $this->setUpCustomerMock($stripe);
        $e = new StripeError('error');
        $item = Mockery::mock('Stripe\InvoiceItem');
        $item->shouldReceive('create')
            ->withArgs([[
                'customer' => $customer->id,
                'currency' => 'usd',
                'unit_amount' => 100,
                'quantity' => 11,
                'description' => 'Invoices/Month - TEST usage for October 2015',
                'period' => [
                    'start' => 1443675600,
                    'end' => 1446353999,
                ],
            ],
            ])
            ->andThrow($e);
        $stripe->invoiceItems = $item;
        $billingSystem->setStripe($stripe);

        $charge = new BillingOneTimeItem(
            price: new Money('usd', 100),
            quantity: 11,
            description: 'TEST usage for October 2015',
            usageType: UsageType::InvoicesPerMonth,
            billingInterval: BillingInterval::Monthly,
            periodStart: new CarbonImmutable('2015-10-01T00:00:00', new CarbonTimeZone('America/Chicago')),
            periodEnd: new CarbonImmutable('2015-10-31T23:59:59', new CarbonTimeZone('America/Chicago')),
        );
        $billingSystem->billLineItem(self::$billingProfile, $charge, false);
    }

    public function testGetBillingState(): void
    {
        $billingSystem = $this->getBillingSystem();

        // set up mocks
        $stripe = Mockery::mock(StripeClient::class);
        $customer = $this->setUpCustomerMock($stripe);
        $customer->default_source = 'card_test';
        $customer->discount = null;

        $subscription = $this->setUpSubscriptionMock($customer->id, $stripe);
        $subscription->cancel_at_period_end = false;
        $subscription->current_period_end = (int) mktime(0, 0, 0, 4, 20, 2044);

        $sources = new stdClass();
        $sources->data = [
            (object) [
                'id' => 'card_test',
                'type' => 'card',
                'card' => (object) [
                    'exp_month' => 4,
                    'exp_year' => 2021,
                    'last4' => '1234',
                    'brand' => 'amex',
                ],
            ],
        ];

        $paymentMethod = Mockery::mock('Stripe\PaymentMethod');
        $paymentMethod->shouldReceive('all')
            ->withArgs([[
                'customer' => $customer->id,
                'type' => 'card',
            ]])
            ->andReturn($sources)
            ->once();
        $stripe->paymentMethods = $paymentMethod;

        $invoice = Mockery::mock('Stripe\Invoice');
        $invoice->shouldReceive('upcoming')
            ->withArgs([[
                'customer' => $customer->id,
            ]])
            ->andReturn((object) ['total' => 10000])
            ->once();
        $stripe->invoices = $invoice;
        $billingSystem->setStripe($stripe);

        $billingState = $billingSystem->getBillingState(self::$billingProfile);
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
        $this->assertEquals(100.0, $billingState->nextChargeAmount);
        $this->assertTrue($billingState->autopay);
    }

    public function testGetBillingHistory(): void
    {
        $billingSystem = $this->getBillingSystem();

        // set up mocks
        $stripe = Mockery::mock(StripeClient::class);
        $customer = $this->setUpCustomerMock($stripe);

        $invoice = new stdClass();
        $invoice->amount_remaining = 0;
        $invoice->created = (int) mktime(0, 0, 0, 4, 20, 2021);
        $invoice->amount_due = 10000;
        $invoice->invoice_pdf = 'https://example.com/invoice_pdf';
        $invoice->hosted_invoice_url = 'https://example.com/pay';
        $invoices = new stdClass();
        $invoices->data = [$invoice];
        $stripeInvoice = Mockery::mock('Stripe\Invoice');
        $stripeInvoice->shouldReceive('all')
            ->withArgs([[
                'customer' => $customer->id,
            ]])
            ->andReturn($invoices)
            ->once();
        $stripe->invoices = $stripeInvoice;
        $billingSystem->setStripe($stripe);

        $expected = [
            [
                'date' => (int) mktime(0, 0, 0, 4, 20, 2021),
                'amount' => 100.0,
                'invoice_url' => 'https://example.com/invoice_pdf',
                'payment_url' => 'https://example.com/pay',
                'error' => null,
            ],
        ];

        $this->assertEquals($expected, $billingSystem->getBillingHistory(self::$billingProfile));
    }

    public function testUpdatePaymentInfoUrl(): void
    {
        $this->assertNull($this->getBillingSystem()->getUpdatePaymentInfoUrl(self::$billingProfile));
    }

    private function getBillingSystem(): StripeBillingSystem
    {
        $billingSystem = new StripeBillingSystem('sk_test_shared');
        $billingSystem->setLogger(self::$logger);

        return $billingSystem;
    }

    /**
     * @return Customer
     */
    private function setUpCustomerMock(StripeClient $stripe): object
    {
        $customer = Mockery::mock();
        $customer->id = self::$billingProfile->stripe_customer;
        $customer->name = 'Test';

        $stripeCustomer = Mockery::mock('Stripe\Customer');
        $stripeCustomer->shouldReceive('retrieve')
            ->withArgs([self::$billingProfile->stripe_customer])
            ->andReturn($customer);
        $stripe->customers = $stripeCustomer;

        return $customer;
    }

    /**
     * @return Subscription|Mockery\LegacyMockInterface
     */
    private function setUpSubscriptionMock(string $customerId, StripeClient $stripe): object
    {
        $subscriptions = new stdClass();
        $subscriptions->data = [];
        $subscriptions->data[0] = Mockery::mock();
        $subscriptions->data[0]->current_period_end = 2345678901;
        $subscriptions->data[0]->status = 'active';
        $subscriptions->data[0]->id = 'activeId';
        $subscriptions->data[0]->items = new stdClass();
        $subscriptions->data[0]->items->data = [];
        $subscriptions->data[0]->items->data[0] = new stdClass();
        $subscriptions->data[0]->items->data[0]->quantity = 1;
        $subscriptions->data[0]->items->data[0]->price = new stdClass();
        $subscriptions->data[0]->items->data[0]->price->recurring = new stdClass();
        $subscriptions->data[0]->items->data[0]->price->recurring->interval = 'month';
        $subscriptions->data[0]->items->data[0]->price->recurring->interval_count = 1;
        $subscriptions->data[0]->items->data[0]->id = 'sure';
        $subscriptions->data[0]->items->data[0]->price->id = 'whatever';

        $stripeSubscription = Mockery::mock('Stripe\Subscription');
        $stripeSubscription->shouldReceive('all')
            ->withArgs([['customer' => $customerId]])
            ->andReturn($subscriptions);
        $stripe->subscriptions = $stripeSubscription;

        return $subscriptions->data[0];
    }
}
