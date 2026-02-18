<?php

namespace App\Tests\PaymentProcessing\Models;

use App\Core\I18n\ValueObjects\Money;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\InitiatedCharge;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\ValueObjects\ChargeValueObject;
use App\Tests\AppTestCase;
use RuntimeException;

class InitiatedChargeTest extends AppTestCase
{
    private static InitiatedCharge $initiatedCharge;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasCard();

        $initiatedCharge = new InitiatedCharge();
        $initiatedCharge->customer = self::$customer;
        $initiatedCharge->correlation_id = 'correlation_id';
        $initiatedCharge->currency = 'USD';
        $initiatedCharge->amount = 100.5;
        $initiatedCharge->gateway = 'gateway';
        $initiatedCharge->application_source = 'customer_portal';
        $initiatedCharge->parameters = (object) ['key' => 'value'];

        self::$initiatedCharge = $initiatedCharge;
    }

    public function testSerialization(): void
    {
        $charge = new ChargeValueObject(
            customer: self::$customer,
            amount: Money::fromDecimal('usd', 100.5),
            gateway: 'mock',
            gatewayId: uniqid(),
            method: PaymentMethod::CREDIT_CARD,
            status: Charge::SUCCEEDED,
            merchantAccount: null,
            source: self::$card,
            description: '',
        );

        self::$initiatedCharge->setCharge($charge);
        self::$initiatedCharge->saveOrFail();

        $chargeValueObject = self::$initiatedCharge->getCharge();
        $this->assertInstanceOf(ChargeValueObject::class, $chargeValueObject);
        $this->assertEquals($charge->customer->id, $chargeValueObject->customer->id);
        $this->assertEquals($charge->amount, $chargeValueObject->amount);
        $this->assertEquals($charge->timestamp, $chargeValueObject->timestamp);
        $this->assertEquals($charge->gateway, $chargeValueObject->gateway);
        $this->assertEquals($charge->gatewayId, $chargeValueObject->gatewayId);
        $this->assertEquals($charge->method, $chargeValueObject->method);
        $this->assertEquals($charge->status, $chargeValueObject->status);
        $this->assertEquals($charge->source?->id, $chargeValueObject->source?->id);
    }

    public function testFailedLegacy(): void
    {
        $this->expectException(RuntimeException::class);

        self::$initiatedCharge->charge = (object) [
            'customer' => self::$customer,
        ];
        self::$initiatedCharge->saveOrFail();

        $chargeValueObject = self::$initiatedCharge->getCharge();
    }
}
