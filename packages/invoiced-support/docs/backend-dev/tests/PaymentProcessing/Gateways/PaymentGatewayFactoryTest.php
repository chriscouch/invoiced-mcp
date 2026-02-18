<?php

namespace App\Tests\PaymentProcessing\Gateways;

use App\PaymentProcessing\Gateways\PaymentGatewayFactory;
use App\PaymentProcessing\Interfaces\PaymentGatewayInterface;
use App\Tests\AppTestCase;

class PaymentGatewayFactoryTest extends AppTestCase
{
    /**
     * @dataProvider provideGateways
     */
    public function testGet(string $gatewayId): void
    {
        $factory = $this->getFactory();
        $this->assertInstanceOf(PaymentGatewayInterface::class, $factory->get($gatewayId));
    }

    public static function provideGateways(): array
    {
        return [
            ['authorizenet'],
            ['braintree'],
            ['cardknox'],
            ['cybersource'],
            ['flywire'],
            ['gocardless'],
            ['intuit'],
            ['lawpay'],
            ['mock'],
            ['moneris'],
            ['nacha'],
            ['nmi'],
            ['orbital'],
            ['payflowpro'],
            ['stripe'],
            ['test'],
            ['vantiv'],
        ];
    }

    private function getFactory(): PaymentGatewayFactory
    {
        return self::getService('test.payment_gateway_factory');
    }
}
