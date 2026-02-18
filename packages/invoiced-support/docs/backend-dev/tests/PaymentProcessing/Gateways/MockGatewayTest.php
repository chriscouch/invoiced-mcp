<?php

namespace App\Tests\PaymentProcessing\Gateways;

use App\AccountsReceivable\Models\Invoice;
use App\Core\I18n\ValueObjects\Money;
use App\PaymentProcessing\Gateways\MockGateway;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Models\PaymentSource;
use App\PaymentProcessing\ValueObjects\CardValueObject;
use App\PaymentProcessing\ValueObjects\ChargeValueObject;
use App\PaymentProcessing\ValueObjects\RefundValueObject;
use App\Tests\AppTestCase;

class MockGatewayTest extends AppTestCase
{
    private MockGateway $gateway;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasCard();
    }

    protected function setUp(): void
    {
        $this->gateway = new MockGateway();
    }

    private function getMerchantAccount(): MerchantAccount
    {
        return new MerchantAccount();
    }

    public function testDI(): void
    {
        $this->assertInstanceOf(MockGateway::class, $this->gateway);
    }

    //
    // PaymentInterface
    //

    public function testCharge(): void
    {
        $merchantAccount = $this->getMerchantAccount();

        $amount = Money::fromDecimal('usd', 10000);
        $parameters = [];
        $charge = $this->gateway->charge(self::$customer, $merchantAccount, $amount, $parameters, '');

        $this->assertInstanceOf(ChargeValueObject::class, $charge);
        $this->assertEquals(self::$customer->id(), $charge->customer->id());
        $this->assertEquals(Charge::SUCCEEDED, $charge->status);
        $this->assertEquals(MockGateway::ID, $charge->gateway);
        $this->assertNotNull($charge->gatewayId);
        $this->assertGreaterThan(0, $charge->timestamp);
        $this->assertEquals($amount->amount, $charge->amount->amount);
        $this->assertEquals('usd', $charge->amount->currency);
        $this->assertEquals(PaymentMethod::CREDIT_CARD, $charge->method);
    }

    //
    // RefundableInterface
    //

    public function testRefund(): void
    {
        $amount = new Money('usd', 10000);
        $refund = $this->gateway->refund(self::$merchantAccount, 'ch_1234', $amount);

        $this->assertInstanceOf(RefundValueObject::class, $refund);
        $this->assertEquals(RefundValueObject::SUCCEEDED, $refund->status);
        $this->assertEquals(MockGateway::ID, $refund->gateway);
        $this->assertNotNull($refund->gatewayId);
        $this->assertGreaterThan(0, $refund->timestamp);
        $this->assertEquals(10000, $refund->amount->amount);
        $this->assertEquals('usd', $refund->amount->currency);
    }

    //
    // PaymentSourceInterface
    //

    public function testVaultSource(): void
    {
        $merchantAccount = $this->getMerchantAccount();

        $parameters = [];
        $source = $this->gateway->vaultSource(self::$customer, $merchantAccount, $parameters);

        $this->assertInstanceOf(CardValueObject::class, $source);
        $this->assertEquals(MockGateway::ID, $source->gateway);
        $this->assertNotNull($source->gatewayId);
        $this->assertTrue($source->chargeable);
    }

    public function testChargeSource(): void
    {
        $invoice = new Invoice(['id' => -10]);
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->setCustomer(self::$customer);

        $amount = new Money('usd', 10000);

        $parameters = [];
        $charge = $this->gateway->chargeSource(self::$card, $amount, $parameters, '', [$invoice]);

        $this->assertInstanceOf(ChargeValueObject::class, $charge);
        $this->assertEquals(self::$customer->id(), $charge->customer->id());
        $this->assertEquals(Charge::SUCCEEDED, $charge->status);
        $this->assertEquals(MockGateway::ID, $charge->gateway);
        $this->assertNotNull($charge->gatewayId);
        $this->assertGreaterThan(0, $charge->timestamp);
        $this->assertEquals(10000, $charge->amount->amount);
        $this->assertEquals('usd', $charge->amount->currency);
        $this->assertEquals(PaymentMethod::CREDIT_CARD, $charge->method);
        $this->assertInstanceOf(PaymentSource::class, $charge->source);
        $this->assertEquals(self::$card->id(), $charge->source->id());
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testDeleteSource(): void
    {
        self::hasCard(MockGateway::ID);

        // this does not do anything
        $this->gateway->deleteSource($this->getMerchantAccount(), self::$card);
    }
}
