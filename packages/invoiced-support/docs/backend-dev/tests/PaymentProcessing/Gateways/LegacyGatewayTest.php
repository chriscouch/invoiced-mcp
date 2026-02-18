<?php

namespace App\Tests\PaymentProcessing\Gateways;

use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Statsd\StatsdClient;
use App\Core\Utils\DebugContext;
use App\PaymentProcessing\Gateways\AbstractGateway;
use App\PaymentProcessing\Gateways\LegacyGateway;
use App\PaymentProcessing\Libs\PaymentServerClient;
use App\PaymentProcessing\Libs\RoutingNumberLookup;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Reconciliation\PaymentSourceReconciler;
use App\PaymentProcessing\ValueObjects\CardValueObject;
use App\PaymentProcessing\ValueObjects\ChargeValueObject;
use App\Tests\AppTestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Mockery;
use Psr\Log\NullLogger;
use stdClass;

class LegacyGatewayTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasMerchantAccount('authorizenet');
        self::hasCard('authorizenet', 'card_12345');
    }

    private function getGateway(): LegacyGateway
    {
        $reconciler = new PaymentSourceReconciler();
        $reconciler->setStatsd(new StatsdClient());
        $paymentServerClient = new PaymentServerClient($reconciler, new DebugContext('test'), '', '', '');
        $paymentServerClient->setLogger(new NullLogger());
        $gatewayLogger = self::getService('test.gateway_logger');
        $routingNumberLookup = Mockery::mock(RoutingNumberLookup::class);

        return new LegacyGateway($paymentServerClient, $gatewayLogger, $routingNumberLookup, $reconciler);
    }

    private function mock(AbstractGateway $gateway, array $responses): void
    {
        $mock = new MockHandler($responses);
        $handler = HandlerStack::create($mock);
        $client = new Client([
            'handler' => $handler,
            'headers' => ['User-Agent' => 'Invoiced/1.0'],
        ]);
        $gateway->getPaymentServerClient()->setClient($client);
    }

    private function getMerchantAccount(): MerchantAccount
    {
        $account = new MerchantAccount(['id' => 12345]);
        $account->gateway = 'test';

        return $account;
    }

    public function getCard(): object
    {
        $card = new stdClass();
        $card->id = null;
        $card->customer = null;
        $card->object = 'card';
        $card->funding = 'unknown';
        $card->exp_month = '9';
        $card->exp_year = '2020';
        $card->brand = 'Visa';
        $card->last4 = '4242';

        return $card;
    }

    //
    // PaymentInterface
    //

    public function testChargeCard(): void
    {
        $gateway = $this->getGateway();

        $result = new stdClass();
        $result->id = 'ch_card_test';
        $result->object = 'charge';
        $result->merchant_account = 12345;
        $result->gateway = 'test';
        $result->status = 'succeeded';
        $result->currency = 'usd';
        $result->amount = 10000;
        $result->timestamp = time();
        $result->message = null;
        $result->source = $this->getCard();
        $responses = [new Response(200, [], (string) json_encode($result))];
        $this->mock($gateway, $responses);

        $merchantAccount = $this->getMerchantAccount();

        $amount = new Money('usd', 10000);
        $parameters = [
            'invoiced_token' => 'tok_test',
        ];
        $charge = $gateway->charge(self::$customer, $merchantAccount, $amount, $parameters, '');

        $this->assertInstanceOf(ChargeValueObject::class, $charge);
        $this->assertEquals(self::$customer->id(), $charge->customer->id());
        $this->assertEquals(Charge::SUCCEEDED, $charge->status);
        $this->assertEquals('test', $charge->gateway);
        $this->assertEquals('ch_card_test', $charge->gatewayId);
        $this->assertEquals($result->timestamp, $charge->timestamp);
        $this->assertEquals(10000, $charge->amount->amount);
        $this->assertEquals('usd', $charge->amount->currency);
        $this->assertEquals(PaymentMethod::CREDIT_CARD, $charge->method);
    }

    public function testChargeInvoice(): void
    {
        $gateway = $this->getGateway();

        $result = new stdClass();
        $result->id = 'ch_card_test';
        $result->object = 'charge';
        $result->merchant_account = 12345;
        $result->gateway = 'test';
        $result->status = 'succeeded';
        $result->currency = 'usd';
        $result->amount = 10000;
        $result->timestamp = time();
        $result->message = null;
        $result->source = $this->getCard();
        $responses = [new Response(200, [], (string) json_encode($result))];
        $this->mock($gateway, $responses);

        $invoice = new Invoice(['id' => 100]);
        $invoice->number = 'INV-0001';
        $invoice->currency = 'usd';

        $merchantAccount = $this->getMerchantAccount();

        $amount = new Money('usd', 10000);
        $parameters = [
            'invoiced_token' => 'tok_test',
        ];
        $charge = $gateway->charge(self::$customer, $merchantAccount, $amount, $parameters, '');

        $this->assertInstanceOf(ChargeValueObject::class, $charge);
        $this->assertEquals(self::$customer->id(), $charge->customer->id());
        $this->assertEquals(Charge::SUCCEEDED, $charge->status);
        $this->assertEquals('test', $charge->gateway);
        $this->assertEquals('ch_card_test', $charge->gatewayId);
        $this->assertEquals($result->timestamp, $charge->timestamp);
        $this->assertEquals(10000, $charge->amount->amount);
        $this->assertEquals('usd', $charge->amount->currency);
        $this->assertEquals(PaymentMethod::CREDIT_CARD, $charge->method);
    }

    public function testChargeEstimate(): void
    {
        $gateway = $this->getGateway();

        $result = new stdClass();
        $result->id = 'ch_card_test';
        $result->object = 'charge';
        $result->merchant_account = 12345;
        $result->gateway = 'test';
        $result->status = 'succeeded';
        $result->currency = 'usd';
        $result->amount = 10000;
        $result->timestamp = time();
        $result->message = null;
        $result->source = $this->getCard();
        $responses = [new Response(200, [], (string) json_encode($result))];
        $this->mock($gateway, $responses);

        $estimate = new Estimate(['id' => 100]);
        $estimate->number = 'EST-0001';
        $estimate->currency = 'usd';
        $estimate->date = time();

        $merchantAccount = $this->getMerchantAccount();

        $amount = new Money('usd', 10000);
        $parameters = [
            'invoiced_token' => 'tok_test',
        ];
        $charge = $gateway->charge(self::$customer, $merchantAccount, $amount, $parameters, 'item_name', [$estimate]);

        $this->assertInstanceOf(ChargeValueObject::class, $charge);
        $this->assertEquals(self::$customer->id(), $charge->customer->id());
        $this->assertEquals(Charge::SUCCEEDED, $charge->status);
        $this->assertEquals('test', $charge->gateway);
        $this->assertEquals('ch_card_test', $charge->gatewayId);
        $this->assertEquals($result->timestamp, $charge->timestamp);
        $this->assertEquals(10000, $charge->amount->amount);
        $this->assertEquals('usd', $charge->amount->currency);
        $this->assertEquals(PaymentMethod::CREDIT_CARD, $charge->method);
    }

    public function testChargeDifferentAmount(): void
    {
        $gateway = $this->getGateway();

        $result = new stdClass();
        $result->id = 'ch_card_test';
        $result->object = 'charge';
        $result->merchant_account = 12345;
        $result->gateway = 'test';
        $result->status = 'succeeded';
        $result->currency = 'usd';
        $result->amount = 5000;
        $result->timestamp = time();
        $result->message = null;
        $result->source = $this->getCard();
        $responses = [new Response(200, [], (string) json_encode($result))];
        $this->mock($gateway, $responses);

        $merchantAccount = $this->getMerchantAccount();

        $amount = new Money('usd', 10000);
        $parameters = [
            'invoiced_token' => 'tok_test',
        ];
        $charge = $gateway->charge(self::$customer, $merchantAccount, $amount, $parameters, '');

        $this->assertInstanceOf(ChargeValueObject::class, $charge);
        $this->assertEquals(self::$customer->id(), $charge->customer->id());
        $this->assertEquals(Charge::SUCCEEDED, $charge->status);
        $this->assertEquals('test', $charge->gateway);
        $this->assertEquals('ch_card_test', $charge->gatewayId);
        $this->assertEquals($result->timestamp, $charge->timestamp);
        $this->assertEquals(5000, $charge->amount->amount);
        $this->assertEquals('usd', $charge->amount->currency);
        $this->assertEquals(PaymentMethod::CREDIT_CARD, $charge->method);
    }

    //
    // PaymentSourceInterface
    //

    public function testVaultSourceCard(): void
    {
        $gateway = $this->getGateway();

        $result = new stdClass();
        $result->id = 'card_1234';
        $result->object = 'card';
        $result->merchant_account = 12345;
        $result->gateway = 'test';
        $result->customer = null;
        $result->brand = 'Visa';
        $result->last4 = '4242';
        $result->exp_month = 2;
        $result->exp_year = 2020;
        $result->funding = 'credit';
        $result->country = 'US';
        $result->name = 'Test Person';
        $responses = [new Response(200, [], (string) json_encode($result))];
        $this->mock($gateway, $responses);

        $merchantAccount = $this->getMerchantAccount();

        $parameters = [
            'invoiced_token' => 'tok_test_card',
        ];
        /** @var CardValueObject $source */
        $source = $gateway->vaultSource(self::$customer, $merchantAccount, $parameters);

        $this->assertInstanceOf(CardValueObject::class, $source);
        $this->assertEquals('test', $source->gateway);
        $this->assertEquals('card_1234', $source->gatewayId);
        $this->assertEquals('Visa', $source->brand);
        $this->assertEquals('4242', $source->last4);
        $this->assertEquals(12345, $source->merchantAccount?->id);
        $this->assertTrue($source->chargeable);
    }
}
