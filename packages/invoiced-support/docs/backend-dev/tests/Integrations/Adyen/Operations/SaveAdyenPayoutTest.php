<?php

namespace App\Tests\Integrations\Adyen\Operations;

use App\Integrations\Adyen\AdyenClient;
use App\Integrations\Adyen\Operations\SaveAdyenPayout;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Models\Payout;
use App\PaymentProcessing\Reconciliation\PayoutReconciler;
use App\Tests\AppTestCase;
use Mockery;

class SaveAdyenPayoutTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasMerchantAccount(AdyenGateway::ID);
    }

    private function getOperation(AdyenClient $adyenClient): SaveAdyenPayout
    {
        $payoutReconciler = new PayoutReconciler(
            self::getService('test.transaction_manager'),
            self::getService('test.merchant_account_ledger'),
            self::getService('test.event_spool'),
        );

        return new SaveAdyenPayout($adyenClient, $payoutReconciler);
    }

    public function testSave(): void
    {
        $adyenClient = Mockery::mock(AdyenClient::class);
        $adyenClient->shouldReceive('getTransfer')
            ->andReturn(
                json_decode((string) file_get_contents(dirname(__DIR__).'/data/completedPayout.json'), true),
            );
        $adyenClient->shouldReceive('getBankAccountName')
            ->andReturn('*1111');

        $operation = $this->getOperation($adyenClient);

        $payout = $operation->save('W259969949', self::$merchantAccount);

        // Validate payout
        $this->assertInstanceOf(Payout::class, $payout);
        $this->assertEquals([
            'amount' => 97.1,
            'arrival_date' => $payout->arrival_date,
            'bank_account_name' => '*1111',
            'created_at' => $payout->created_at,
            'currency' => 'usd',
            'description' => 'Flywire Payout',
            'failure_message' => null,
            'gross_amount' => 97.1,
            'id' => $payout->id,
            'initiated_at' => $payout->initiated_at,
            'merchant_account_id' => self::$merchantAccount->id,
            'merchant_account_transaction_id' => $payout->merchant_account_transaction?->id,
            'object' => 'payout',
            'pending_amount' => 0,
            'reference' => '3KA47F66EP1ETPB1',
            'statement_descriptor' => null,
            'status' => 'completed',
            'updated_at' => $payout->updated_at,
            'modification_reference' => null
        ], $payout->toArray());
        $this->assertEquals('2025-03-25', $payout->initiated_at->format('Y-m-d'));
        $this->assertEquals('2025-03-26', $payout->arrival_date?->format('Y-m-d'));
    }

    public function testSaveReturned(): void
    {
        $adyenClient = Mockery::mock(AdyenClient::class);
        $adyenClient->shouldReceive('getTransfer')
            ->andReturn(
                json_decode((string) file_get_contents(dirname(__DIR__).'/data/returnedPayout.json'), true),
            );
        $adyenClient->shouldReceive('getBankAccountName')
            ->andReturn('*1111');

        $operation = $this->getOperation($adyenClient);

        $payout = $operation->save('1QALCW66QKYIB54A', self::$merchantAccount);

        // Validate payout
        $this->assertInstanceOf(Payout::class, $payout);
        $this->assertEquals([
            'amount' => 19.4,
            'arrival_date' => $payout->arrival_date,
            'bank_account_name' => '*1111',
            'created_at' => $payout->created_at,
            'currency' => 'usd',
            'description' => 'Flywire Payout',
            'failure_message' => null,
            'gross_amount' => 19.4,
            'id' => $payout->id,
            'initiated_at' => $payout->initiated_at,
            'merchant_account_id' => self::$merchantAccount->id,
            'merchant_account_transaction_id' => $payout->merchant_account_transaction?->id,
            'object' => 'payout',
            'pending_amount' => 0,
            'reference' => '1QALCW66QKYIB54A',
            'statement_descriptor' => null,
            'status' => 'failed',
            'updated_at' => $payout->updated_at,
            'modification_reference' => null
        ], $payout->toArray());
        $this->assertEquals('2025-04-24', $payout->initiated_at->format('Y-m-d'));
        $this->assertEquals('2025-04-24', $payout->arrival_date?->format('Y-m-d'));
    }
}
