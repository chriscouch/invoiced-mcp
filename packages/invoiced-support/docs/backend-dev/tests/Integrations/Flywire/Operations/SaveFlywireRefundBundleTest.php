<?php

namespace App\Tests\Integrations\Flywire\Operations;

use App\Integrations\Flywire\Enums\FlywireRefundBundleStatus;
use App\Integrations\Flywire\FlywirePrivateClient;
use App\Integrations\Flywire\Models\FlywireRefundBundle;
use App\Integrations\Flywire\Operations\SaveFlywireRefundBundle;
use App\Tests\AppTestCase;
use Mockery;

class SaveFlywireRefundBundleTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    private function getOperation(FlywirePrivateClient $client): SaveFlywireRefundBundle
    {
        $operation = new SaveFlywireRefundBundle($client);
        $operation->setLogger(self::$logger);

        return $operation;
    }

    public function testProcess(): void
    {
        $client = Mockery::mock(FlywirePrivateClient::class);
        $client->shouldReceive('getRefundBundle')
            ->andReturn([
                'id' => 'BUDRC46B3405',
                'recipient_id' => 'UUO',
                'status' => 'pending',
                'notifications_url' => 'https://staging.invoiced.com/flywire/refund/callback',
                'amount' => [
                    'value' => 171100,
                    'currency' => [
                        'code' => 'USD',
                    ],
                ],
                'reception' => [
                    'date' => null,
                    'bank_reference' => null,
                    'account_number' => null,
                    'amount' => null,
                    'currency' => null,
                ],
            ]);
        $operation = $this->getOperation($client);
        $operation->sync([
            'id' => 'BUDRC46B3405',
            'portals' => ['UUO', 'DTT'],
            'created_at' => '2024-09-17T19:47:38Z',
        ]);

        /** @var FlywireRefundBundle[] $flyRefundBundle */
        $flyRefundBundle = FlywireRefundBundle::all()->toArray();
        $this->assertEquals(1, count($flyRefundBundle));
        $this->assertEquals('usd', $flyRefundBundle[0]->currency);
        $this->assertEquals(FlywireRefundBundleStatus::Pending, $flyRefundBundle[0]->status);

        $client = Mockery::mock(FlywirePrivateClient::class);
        $client->shouldReceive('getRefundBundle')
            ->andReturn([
                'id' => 'BUDRC46B3405',
                'recipient_id' => 'UUO',
                'status' => 'received',
                'notifications_url' => 'https://staging.invoiced.com/flywire/refund/callback',
                'amount' => [
                    'value' => 171100,
                    'currency' => [
                        'code' => 'EUR',
                    ],
                ],
                'reception' => [
                    'date' => null,
                    'bank_reference' => null,
                    'account_number' => null,
                    'amount' => null,
                    'currency' => null,
                ],
            ]);
        $operation = $this->getOperation($client);
        $operation->sync([
            'id' => 'BUDRC46B3405',
            'portals' => ['UUO', 'DTT'],
            'created_at' => '2024-09-17T19:47:38Z',
        ]);

        /** @var FlywireRefundBundle[] $flyRefundBundle */
        $flyRefundBundle = FlywireRefundBundle::all()->toArray();
        $this->assertEquals('eur', $flyRefundBundle[0]->currency);
        $this->assertEquals(FlywireRefundBundleStatus::Received, $flyRefundBundle[0]->status);
    }
}
