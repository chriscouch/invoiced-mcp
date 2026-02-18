<?php

namespace App\Tests\PaymentProcessing\Gateways;

use App\AccountsReceivable\Models\Customer;
use App\Core\Statsd\StatsdClient;
use App\Core\Utils\DebugContext;
use App\PaymentProcessing\Gateways\StripeGateway;
use App\PaymentProcessing\Libs\PaymentServerClient;
use App\PaymentProcessing\Libs\RoutingNumberLookup;
use App\PaymentProcessing\Reconciliation\PaymentSourceReconciler;
use App\PaymentProcessing\ValueObjects\PaymentGatewayConfiguration;
use App\Tests\AppTestCase;
use InvalidArgumentException;
use Mockery;
use Psr\Log\NullLogger;
use stdClass;
use Stripe\Exception\AuthenticationException as StripeError;
use Stripe\Exception\InvalidRequestException;
use Stripe\StripeClient;

class StripeGatewayTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::getService('test.redis')->del('invoicedtest:stripe_event.test');

        self::hasCompany();
        self::hasMerchantAccount(StripeGateway::ID, 'TEST_STRIPE_USER_ID', ['key' => 'CONNECT_KEY']);
        self::acceptsCreditCards(StripeGateway::ID);

        self::hasCustomer();
        self::$customer->metadata = (object) ['stripe_customer_id' => 'cust_test'];
        self::$customer->saveOrFail();
        self::hasInvoice();
        self::hasCard(StripeGateway::ID, 'card_test');
        self::hasBankAccount(StripeGateway::ID);
        self::$customer->setDefaultPaymentSource(self::$bankAccount);
    }

    private function getStripeGateway(): StripeGateway
    {
        $reconciler = new PaymentSourceReconciler();
        $reconciler->setStatsd(new StatsdClient());
        $paymentServerClient = new PaymentServerClient($reconciler, new DebugContext('test'), '', '', '');
        $paymentServerClient->setLogger(new NullLogger());
        $gatewayLogger = self::getService('test.gateway_logger');
        $routingNumberLookup = Mockery::mock(RoutingNumberLookup::class);
        $gateway = new StripeGateway($paymentServerClient, $gatewayLogger, $routingNumberLookup, $reconciler);
        $gateway->setLogger(new NullLogger());

        return $gateway;
    }

    public function testUseMerchantAccount(): void
    {
        $reconciler = new PaymentSourceReconciler();
        $reconciler->setStatsd(new StatsdClient());
        $gateway = $this->getStripeGateway();

        $stripe = $gateway->getStripe(self::$merchantAccount->toGatewayConfiguration());
        $this->assertEquals('CONNECT_KEY', $stripe->getApiKey());
    }

    public function testUseMerchantAccountNotSetup(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $gateway = $this->getStripeGateway();
        $gateway->getStripe(new PaymentGatewayConfiguration('stripe', (object) []));
    }

    public function testGetStripeCustomerParams(): void
    {
        $gateway = $this->getStripeGateway();
        $expected = [
            'name' => 'Sherlock',
            'description' => 'CUST-00001',
            'email' => 'sherlock@example.com',
            'address' => [
                'line1' => 'Test',
                'line2' => 'Address',
                'city' => 'Austin',
                'state' => 'TX',
                'postal_code' => '78701',
                'country' => 'US',
            ],
            'metadata' => [
                'invoiced.customer' => self::$customer->id(),
            ],
        ];

        $this->assertEquals($expected, $gateway->buildStripeCustomerParams(self::$customer));
    }

    public function testGetStripeCustomer(): void
    {
        // Setup - Models, Mocks, etc.

        $gateway = $this->getStripeGateway();

        $customer = new Customer();
        $customer->name = 'testGetStripeCustomerNotExists';
        $customer->saveOrFail();

        $newCustomer = new stdClass();
        $newCustomer->id = 'cust_test';
        $newCustomer->email = 'test@example.com';

        $staticCustomer = Mockery::mock('Stripe\Customer');
        $staticCustomer->shouldReceive('create')
            ->andReturn($newCustomer)
            ->once();
        $stripe = Mockery::mock(StripeClient::class);
        $stripe->customers = $staticCustomer;

        // Call the method being tested

        $this->assertEquals($newCustomer, $gateway->findOrCreateStripeCustomer($customer, $stripe));

        // Verify the results

        $this->assertEquals(['stripe_customer_id' => 'cust_test'], (array) $customer->refresh()->metadata);
    }

    public function testGetStripeCustomerNotExists(): void
    {
        // Setup - Models, Mocks, etc.

        $gateway = $this->getStripeGateway();

        $customer = new Customer();
        $customer->name = 'testGetStripeCustomerNotExists';
        $customer->metadata = (object) ['stripe_customer_id' => 'cust_test_getStripeCustomerNotExists'];
        $customer->saveOrFail();

        $staticCustomer = Mockery::mock('Stripe\Customer');
        $staticCustomer->shouldReceive('retrieve')
            ->withArgs(['cust_test_getStripeCustomerNotExists'])
            ->andThrow(InvalidRequestException::factory('No such customer: cust_test_getStripeCustomerNotExists', 404));

        $newCustomer = new stdClass();
        $newCustomer->id = 'cust_new_getStripeCustomerNotExists';
        $staticCustomer->shouldReceive('create')
            ->andReturn($newCustomer);
        $stripe = Mockery::mock(StripeClient::class);
        $stripe->customers = $staticCustomer;

        // Call the method being tested

        $this->assertEquals($newCustomer, $gateway->findOrCreateStripeCustomer($customer, $stripe));

        // Verify the results

        $this->assertEquals(['stripe_customer_id' => 'cust_new_getStripeCustomerNotExists'], (array) $customer->refresh()->metadata);
    }

    public function testGetStripeCustomerFail(): void
    {
        // Setup - Models, Mocks, etc.

        $gateway = $this->getStripeGateway();

        $customer = new Customer();
        $customer->name = 'testGetStripeCustomerFail';
        $customer->metadata = (object) ['stripe_customer_id' => 'cust_test_getStripeCustomerFail'];
        $customer->saveOrFail();

        $staticCustomer = Mockery::mock('Stripe\Customer');
        $staticCustomer->shouldReceive('retrieve')
            ->withArgs(['cust_test_getStripeCustomerFail'])
            ->andThrow(new StripeError(''));
        $stripe = Mockery::mock(StripeClient::class);
        $stripe->customers = $staticCustomer;

        // Call the method being tested

        $this->assertNull($gateway->findOrCreateStripeCustomer($customer, $stripe));
    }
}
