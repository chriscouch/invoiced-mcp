<?php

namespace App\Tests\Integrations\Flywire\Syncs;

use App\Integrations\Flywire\Enums\FlywireRefundStatus;
use App\Integrations\Flywire\FlywirePrivateClient;
use App\Integrations\Flywire\Models\FlywireRefund;
use App\Integrations\Flywire\Operations\SaveFlywireRefund;
use App\Integrations\Flywire\Syncs\FlywireRefundSync;
use App\PaymentProcessing\Gateways\FlywireGateway;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\Refund;
use App\Tests\AppTestCase;
use Mockery;

class FlywireRefundSyncTest extends AppTestCase
{
    private static Refund $refund1;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasMerchantAccount(FlywireGateway::ID, 'gateway4_'.time());

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
        self::$refund1->currency = 'usd';
        self::$refund1->amount = 100;
        self::$refund1->status = 'pending';
        self::$refund1->gateway = FlywireGateway::ID;
        self::$refund1->gateway_id = 'RPTUE0D63641';
        self::$refund1->saveOrFail();
    }

    /**
     * @dataProvider dataProvider
     */
    public function testSync(string $data, array $expected): void
    {
        $client = Mockery::mock(FlywirePrivateClient::class);
        $client->shouldReceive('getRefunds')
            ->andReturn([
                'refunds' => [
                    [
                        'id' => 'RPTUE0D63641',
                        'sender' => [
                            'id' => 'UUO',
                        ],
                    ],
                ],
            ])->once();
        $input = json_decode($data, true);
        $client->shouldReceive('getRefund')->andReturn($input);
        $operation = new SaveFlywireRefund($client);

        $sync = new FlywireRefundSync($client, $operation);

        FlywireRefund::where('refund_id', 'RPTUE0D63641')->delete();
        self::$refund1->status = 'pending';
        self::$refund1->saveOrFail();

        $sync->sync(self::$merchantAccount, ['UUO'], false);

        self::$refund1->refresh();
        $this->assertEquals($expected['status'], self::$refund1->status);

        $refund = FlywireRefund::where('refund_id', 'RPTUE0D63641')->one();
        $this->assertEquals(FlywireRefundStatus::fromString($input['status']), $refund->status);
    }

    public function dataProvider(): array
    {
        return [
            'processed' => [
                '{
    "id": "RPTUE0D63641",
    "payment": {
        "id": "PTU146221637"
    },
    "external_reference": "a-reference",
    "bundle": {
        "id": "BUDR0AEA9E47"
     },
    "created_at": "2024-09-17T19:47:38Z",
    "sender": {
        "id": "UUO"
    },
    "status": "finished",
    "amount": {
        "value": "4800",
        "currency": {
            "code": "EUR"
         }
     },
    "amount_to": {
        "value": "4800",
        "currency": {
            "code": "EUR"
         }
     }
  }',
                [
                    'status' => 'succeeded',
                ],
            ],
        ];
    }
}
