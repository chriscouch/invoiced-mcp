<?php

namespace App\Tests\PaymentProcessing\Reconciliation;

use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\EventSpool;
use App\PaymentProcessing\Enums\DisputeStatus;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\Dispute;
use App\PaymentProcessing\Reconciliation\DisputeReconciler;
use App\Tests\AppTestCase;

class DisputeReconcilerTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasMerchantAccount(AdyenGateway::ID);
    }

    private function getOperation(): DisputeReconciler
    {
        return new DisputeReconciler(self::getService('test.transaction_manager'), self::getService('test.event_spool'));
    }

    public function testReconcile(): void
    {
        EventSpool::enable();
        $operation = $this->getOperation();

        $charge = new Charge();
        $charge->currency = 'usd';
        $charge->amount = 100;
        $charge->status = Charge::SUCCEEDED;
        $charge->gateway = AdyenGateway::ID;
        $charge->gateway_id = 'ch_test';
        $charge->saveOrFail();

        $dispute = $operation->reconcile([
            'charge_gateway_id' => 'ch_test',
            'gateway_id' => 'ABC123',
            'gateway' => AdyenGateway::ID,
            'currency' => 'usd',
            'amount' => 100,
            'status' => DisputeStatus::Unresponded,
            'reason' => 'test reason',
        ]);

        // Validate dispute
        $this->assertInstanceOf(Dispute::class, $dispute);
        $this->assertEquals([
            'amount' => 100.0,
            'charge_id' => $charge->id,
            'created_at' => $dispute->created_at,
            'currency' => 'usd',
            'defense_reason' => null,
            'gateway' => 'flywire_payments',
            'gateway_id' => 'ABC123',
            'id' => $dispute->id,
            'merchant_account_transaction_id' => null,
            'object' => 'dispute',
            'reason' => 'test reason',
            'status' => 'Unresponded',
            'updated_at' => $dispute->updated_at,
        ], $dispute->toArray());

        // Validate charge is marked disputed
        $this->assertTrue($charge->refresh()->disputed);

        // Validate activity log entry
        $this->assertHasEvent($dispute, EventType::DisputeCreated);
    }
}
