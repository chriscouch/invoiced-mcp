<?php

namespace App\Tests\PaymentProcessing\Models;

use App\PaymentProcessing\Enums\DisputeStatus;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Gateways\FlywireGateway;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\Dispute;
use App\Tests\ModelTestCase;

/**
 * @extends ModelTestCase<Dispute>
 */
class DisputeTest extends ModelTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCustomer();
    }

    protected function getModelCreate(): Dispute
    {
        $charge = new Charge();
        $charge->customer = self::$customer;
        $charge->currency = 'usd';
        $charge->amount = 100;
        $charge->status = Charge::PENDING;
        $charge->gateway = FlywireGateway::ID;
        $charge->gateway_id = 'PTU146221637';
        $charge->last_status_check = 0;
        $charge->saveOrFail();

        $dispute = new Dispute();
        $dispute->charge = $charge;
        $dispute->amount = 100;
        $dispute->currency = 'usd';
        $dispute->status = DisputeStatus::Undefended;
        $dispute->gateway = AdyenGateway::ID;
        $dispute->gateway_id = '1234';
        $dispute->reason = 'Fraudulent';

        return $dispute;
    }

    protected function getExpectedToArray($model, array &$output): array
    {
        return [
            'amount' => 100.0,
            'charge_id' => $model->charge_id,
            'created_at' => $model->created_at,
            'currency' => 'usd',
            'defense_reason' => null,
            'gateway' => 'flywire_payments',
            'gateway_id' => '1234',
            'id' => $model->id,
            'merchant_account_transaction_id' => null,
            'object' => 'dispute',
            'reason' => 'Fraudulent',
            'status' => 'Undefended',
            'updated_at' => $model->updated_at,
        ];
    }

    protected function getModelEdit($model): Dispute
    {
        $model->status = DisputeStatus::Accepted;

        return $model;
    }
}
