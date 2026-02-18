<?php

namespace App\Tests\Integrations\Adyen;

use App\EntryPoint\QueueJob\ProcessAdyenRefundWebhookJob;
use App\PaymentProcessing\Exceptions\RefundException;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\Refund;
use App\PaymentProcessing\ValueObjects\RefundValueObject;
use App\Tests\AppTestCase;
use Mockery;

class ProcessAdyenRefundWebhookJobTest extends AppTestCase
{
    public static Refund $refundModel;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasMerchantAccount(AdyenGateway::ID);

        $reference = '9i17gboe04f5t13a8'.time();

        $charge = new Charge();
        $charge->currency = 'usd';
        $charge->amount = 100;
        $charge->status = Charge::SUCCEEDED;
        $charge->gateway = AdyenGateway::ID;
        $charge->gateway_id = 'ch_test';
        $charge->merchant_account = self::$merchantAccount;
        $charge->saveOrFail();

        $refund = new Refund();
        $refund->charge = $charge;
        $refund->amount = $charge->amount;
        $refund->currency = $charge->currency;
        $refund->status = RefundValueObject::PENDING;
        $refund->gateway = $charge->gateway;
        $refund->gateway_id = $reference;
        $refund->saveOrFail();

        self::$refundModel = $refund;
    }

    /**
     * @dataProvider performProvider
     */
    public function testPerform(array $data, string $expectedStatus, callable $mock, ?string $expectedPspReference = null): void
    {
        $refund = self::$refundModel;
        $refund->status = RefundValueObject::PENDING;
        $refund->saveOrFail();
        $gateway = Mockery::mock(AdyenGateway::class);
        $mock($gateway);
        $job = new ProcessAdyenRefundWebhookJob($gateway);
        if (!isset($data['pspReference'])) {
            $data['pspReference'] = $refund->gateway_id;
        }
        if (!$expectedPspReference) {
            $expectedPspReference = $refund->gateway_id;
        }
        $job->args = ['event' => $data];

        $job->perform();
        $refund->refresh();
        $this->assertEquals($expectedStatus, $refund->status);
        $this->assertEquals($expectedPspReference, $refund->gateway_id);
    }

    public function performProvider(): array
    {
        return [
            [
                [
                    'pspReference' => 'missing',
                    'success' => 'true',
                ],
                RefundValueObject::PENDING,
                fn ($gateway) => null,
            ],
            [
                [
                    'eventCode' => 'CANCELLATION',
                    'success' => 'true',
                ],
                RefundValueObject::SUCCEEDED,
                fn ($gateway) => null,
            ],
            [
                [
                    'eventCode' => 'REFUND_FAILED',
                    'success' => 'true',
                ],
                RefundValueObject::FAILED,
                fn ($gateway) => null,
            ],
            [
                [
                    'eventCode' => 'REFUND',
                    'success' => 'false',
                ],
                RefundValueObject::FAILED,
                fn ($gateway) => null,
            ],
            [
                [
                    'eventCode' => 'CANCELLATION',
                    'success' => 'false',
                    'merchantReference' => '1234',
                ],
                RefundValueObject::FAILED,
                fn ($gateway) => $gateway->shouldReceive('credit')
                    ->once()
                    ->andThrow(new RefundException('Credit failed')),
            ],
            [
                [
                    'eventCode' => 'CANCELLATION',
                    'success' => 'false',
                    'merchantReference' => '1234',
                ],
                RefundValueObject::PENDING,
                fn ($gateway) => $gateway->shouldReceive('credit')
                    ->andReturn('pspReference1234')
                    ->once(),
                'pspReference1234',
            ],
        ];
    }
}
