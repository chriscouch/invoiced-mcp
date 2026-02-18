<?php

namespace App\Tests\Integrations\Flywire\Operations;

use App\Integrations\Flywire\Enums\FlywireRefundStatus;
use App\Integrations\Flywire\FlywirePrivateClient;
use App\Integrations\Flywire\Models\FlywireRefund;
use App\Integrations\Flywire\Operations\SaveFlywireRefund;
use App\PaymentProcessing\Gateways\FlywireGateway;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\Refund;
use App\Tests\AppTestCase;
use Mockery;

class SaveFlywireRefundTest extends AppTestCase
{
    public static Refund $refund1;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();

        $charge = new Charge();
        $charge->customer = self::$customer;
        $charge->currency = 'usd';
        $charge->amount = 100;
        $charge->status = Charge::PENDING;
        $charge->gateway = FlywireGateway::ID;
        $charge->gateway_id = 'PTU146221637';
        $charge->last_status_check = 0;
        $charge->saveOrFail();

        self::$refund1 = new Refund();
        self::$refund1->charge = $charge;
        self::$refund1->amount = $charge->amount;
        self::$refund1->currency = $charge->currency;
        self::$refund1->status = 'succeeded';
        self::$refund1->gateway = $charge->gateway;
        self::$refund1->gateway_id = 'RPTUE0D63641';
        self::$refund1->saveOrFail();
    }

    private function getOperation(FlywirePrivateClient $client): SaveFlywireRefund
    {
        $operation = new SaveFlywireRefund($client);
        $operation->setLogger(self::$logger);

        return $operation;
    }

    public function testProcess(): void
    {
        $client = Mockery::mock(FlywirePrivateClient::class);
        $client->shouldReceive('getRefund')
            ->andReturn([
                'id' => 'test1',
                'payment' => [
                    'id' => 'test2',
                ],
                'created_at' => '2024-09-17T19:47:38Z',
                'sender' => [
                    'id' => 'UUO',
                ],
                'amount' => [
                    'value' => 100,
                    'currency' => [
                        'code' => 'USD',
                    ],
                ],
                'amount_to' => [
                    'value' => 100,
                    'currency' => [
                        'code' => 'USD',
                    ],
                ],
                'status' => 'pending',
            ]);
        $operation = $this->getOperation($client);
        $operation->sync('test1', 'UUO');

        /** @var FlywireRefund[] $flyRefund */
        $flyRefund = FlywireRefund::all()->toArray();
        $this->assertEquals(1, count($flyRefund));
        $this->assertEquals('usd', $flyRefund[0]->currency);
        $this->assertEquals(FlywireRefundStatus::Pending, $flyRefund[0]->status);

        $client = Mockery::mock(FlywirePrivateClient::class);
        $client->shouldReceive('getRefund')
            ->andReturn([
                'id' => 'test1',
                'payment' => [
                    'id' => 'test2',
                ],
                'created_at' => '2024-09-17T19:47:38Z',
                'sender' => [
                    'id' => 'UUO',
                ],
                'amount' => [
                    'value' => 100,
                    'currency' => [
                        'code' => 'EUR',
                    ],
                ],
                'amount_to' => [
                    'value' => 100,
                    'currency' => [
                        'code' => 'EUR',
                    ],
                ],
                'status' => 'finished',
            ]);
        $operation = $this->getOperation($client);
        $operation->sync('test1', 'UUO');

        /** @var FlywireRefund[] $flyRefund */
        $flyRefund = FlywireRefund::all()->toArray();
        $this->assertEquals('eur', $flyRefund[0]->currency);
        $this->assertEquals(FlywireRefundStatus::Finished, $flyRefund[0]->status);
    }
}
