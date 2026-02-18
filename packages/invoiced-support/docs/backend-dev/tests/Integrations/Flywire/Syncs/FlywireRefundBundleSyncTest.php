<?php

namespace App\Tests\Integrations\Flywire\Syncs;

use App\Integrations\Flywire\Enums\FlywireRefundBundleStatus;
use App\Integrations\Flywire\FlywirePrivateClient;
use App\Integrations\Flywire\Models\FlywireRefundBundle;
use App\Integrations\Flywire\Operations\SaveFlywireRefundBundle;
use App\Integrations\Flywire\Syncs\FlywireRefundBundleSync;
use App\Tests\AppTestCase;
use Mockery;

class FlywireRefundBundleSyncTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasMerchantAccount('flywire');
    }

    /**
     * @dataProvider dataProvider
     */
    public function testSync(string $data): void
    {
        $client = Mockery::mock(FlywirePrivateClient::class);
        $client->shouldReceive('getRefundBundles')
            ->andReturn([
                    'bundles' => [
                        [
                            'id' => 'BUDRC46B3405',
                            'portals' => ['UUO', 'DTT'],
                            'created_at' => '2024-09-17T19:47:38Z',
                        ],
                    ],
                ]
            )->once();
        $input = json_decode($data, true);
        $client->shouldReceive('getRefundBundle')->andReturn($input);
        $operation = new SaveFlywireRefundBundle($client);

        $sync = new FlywireRefundBundleSync($client, $operation);

        FlywireRefundBundle::where('bundle_id', 'BUDRC46B3405')->delete();

        $sync->sync(self::$merchantAccount, ['UUO'], false);

        $refundBundle = FlywireRefundBundle::where('bundle_id', 'BUDRC46B3405')->one();
        $this->assertEquals(FlywireRefundBundleStatus::fromString($input['status']), $refundBundle->status);
    }

    public function dataProvider(): array
    {
        return [
            'received' => [
                '{
    "id": "BUDRC46B3405",
    "recipient_id": "UUO",
    "status": "received",
    "notifications_url": "https://staging.invoiced.com/flywire/refund/callback",
    "amount": {
        "value": 171100,
        "currency": {
            "code": "EUR"
        }
    },
    "reception": {
        "date": null,
        "bank_reference": null,
        "account_number": null,
        "amount": null,
        "currency": null
    }
  }',
            ],
        ];
    }
}
