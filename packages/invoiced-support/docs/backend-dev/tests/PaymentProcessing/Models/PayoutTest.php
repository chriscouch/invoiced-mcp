<?php

namespace App\Tests\PaymentProcessing\Models;

use App\Core\Utils\RandomString;
use App\PaymentProcessing\Enums\PayoutStatus;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Models\Payout;
use App\Tests\ModelTestCase;
use Carbon\CarbonImmutable;

/**
 * @extends ModelTestCase<Payout>
 */
class PayoutTest extends ModelTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasMerchantAccount(AdyenGateway::ID);
    }

    protected function getModelCreate(): Payout
    {
        $payout = new Payout();
        $payout->merchant_account = self::$merchantAccount;
        $payout->reference = RandomString::generate();
        $payout->currency = 'usd';
        $payout->amount = 100;
        $payout->pending_amount = 0;
        $payout->gross_amount = 100;
        $payout->description = 'Payout';
        $payout->status = PayoutStatus::Pending;
        $payout->bank_account_name = 'Chase *1234';
        $payout->initiated_at = CarbonImmutable::now();

        return $payout;
    }

    protected function getExpectedToArray($model, array &$output): array
    {
        return [
            'amount' => 100.0,
            'arrival_date' => null,
            'bank_account_name' => 'Chase *1234',
            'created_at' => $model->created_at,
            'currency' => 'usd',
            'description' => 'Payout',
            'failure_message' => null,
            'gross_amount' => 100,
            'id' => $model->id,
            'initiated_at' => $model->initiated_at,
            'merchant_account_id' => self::$merchantAccount->id,
            'merchant_account_transaction_id' => null,
            'object' => 'payout',
            'pending_amount' => 0,
            'reference' => $model->reference,
            'statement_descriptor' => null,
            'status' => 'pending',
            'updated_at' => $model->updated_at,
            'modification_reference' => null
        ];
    }

    protected function getModelEdit($model): Payout
    {
        $model->status = PayoutStatus::Completed;

        return $model;
    }
}
