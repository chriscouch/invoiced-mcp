<?php

namespace App\Tests\PaymentProcessing\Operations;

use App\Core\I18n\ValueObjects\Money;
use App\PaymentProcessing\Exceptions\RefundException;
use App\PaymentProcessing\Gateways\PaymentGatewayFactory;
use App\PaymentProcessing\Interfaces\PaymentGatewayInterface;
use App\PaymentProcessing\Interfaces\RefundInterface;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Operations\ProcessRefund;
use App\PaymentProcessing\ValueObjects\RefundValueObject;
use App\Tests\AppTestCase;
use Mockery;

class ProcessRefundTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
        self::hasTransaction();
        self::hasCard();
    }

    private function getOperation(): ProcessRefund
    {
        return self::getService('test.process_refund');
    }

    public function testRefundInvalidStatus(): void
    {
        $this->expectException(RefundException::class);
        $this->expectExceptionMessage('Refunds are not available for this charge because it has not cleared.');

        $refund = $this->getOperation();
        $charge = new Charge();
        $charge->status = 'failed';
        $refund->refund($charge, new Money('usd', 100));
    }

    public function testRefundInvalidAmount(): void
    {
        $this->expectException(RefundException::class);
        $this->expectExceptionMessage('The refund amount cannot exceed the original charge amount.');

        $refund = $this->getOperation();
        $charge = new Charge();
        $charge->currency = 'usd';
        $charge->amount = 1;
        $charge->status = 'succeeded';
        $refund->refund($charge, new Money('usd', 10000000));
    }

    public function testPartialRefundNotSettled(): void
    {
        $this->expectException(RefundException::class);
        $this->expectExceptionMessage('Partial refunds are not supported before the payment has settled. You must refund the payment in full or wait until the next day when the payment has settled.');

        $refund = $this->getOperation();
        $charge = new Charge();
        $charge->currency = 'eur';
        $charge->amount = 100;
        $charge->status = 'succeeded';
        $charge->created_at = time();
        $charge->setPaymentSource(self::$card);
        $refund->refund($charge, new Money('eur', 1000));
    }

    public function testRefund(): void
    {
        $gateway = Mockery::mock(PaymentGatewayInterface::class.','.RefundInterface::class);
        $gateway->shouldReceive('validateConfiguration');
        $gateway->shouldReceive('refund')
            ->andReturn(new RefundValueObject(
                amount: new Money('eur', 1000),
                timestamp: (int) mktime(0, 0, 0, 12, 2, 2016),
                gateway: 'invoiced',
                gatewayId: 're_1235',
                status: RefundValueObject::SUCCEEDED,
            ));

        $gatewayFactory = Mockery::mock(PaymentGatewayFactory::class);
        $gatewayFactory->shouldReceive('get')->andReturn($gateway);
        $refundCommand = $this->getOperation();
        $refundCommand->setGatewayFactory($gatewayFactory);

        $charge = new Charge();
        $charge->customer = self::$customer;
        $charge->currency = 'eur';
        $charge->gateway = 'refund_gateway';
        $charge->gateway_id = 'test';
        $charge->amount = 100;
        $charge->last_status_check = time();
        $charge->status = 'succeeded';
        $charge->setPaymentSource(self::$card);
        $charge->saveOrFail();
        $charge->created_at = strtotime('-1 day'); // needed to pass validation with partial refund

        /** @var RefundValueObject $refund */
        $refund = $refundCommand->refund($charge, new Money('eur', 1000));
        $this->assertEquals(10.0, $refund->amount);

        $this->assertFalse($charge->refunded);
        $this->assertEquals(10.0, $charge->amount_refunded);
    }

    public function testRefundFail(): void
    {
        $this->expectException(RefundException::class);
        $this->expectExceptionMessage('error');

        $e = new RefundException('error');
        $gateway = Mockery::mock(PaymentGatewayInterface::class.','.RefundInterface::class);
        $gateway->shouldReceive('validateConfiguration');
        $gateway->shouldReceive('refund')
            ->andThrow($e);

        $gatewayFactory = Mockery::mock(PaymentGatewayFactory::class);
        $gatewayFactory->shouldReceive('get')->andReturn($gateway);
        $refund = $this->getOperation();
        $refund->setGatewayFactory($gatewayFactory);

        $charge = new Charge();
        $charge->customer = self::$customer;
        $charge->currency = 'usd';
        $charge->gateway = 'refund_fail';
        $charge->gateway_id = 'test';
        $charge->amount = 100;
        $charge->last_status_check = time();
        $charge->status = 'succeeded';
        $charge->setPaymentSource(self::$card);
        $charge->saveOrFail();

        $refund->refund($charge, new Money('usd', 10000));
    }
}
