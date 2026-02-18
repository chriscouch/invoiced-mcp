<?php

namespace App\Tests\PaymentProcessing\Models;

use App\Core\Utils\RandomString;
use App\PaymentProcessing\Enums\MerchantAccountTransactionType;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Models\MerchantAccountTransaction;
use App\Tests\ModelTestCase;
use Carbon\CarbonImmutable;

/**
 * @extends ModelTestCase<MerchantAccountTransaction>
 */
class MerchantAccountTransactionTest extends ModelTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasMerchantAccount(AdyenGateway::ID);
    }

    protected function getModelCreate(): MerchantAccountTransaction
    {
        $transaction = new MerchantAccountTransaction();
        $transaction->merchant_account = self::$merchantAccount;
        $transaction->reference = RandomString::generate();
        $transaction->type = MerchantAccountTransactionType::Payment;
        $transaction->currency = 'usd';
        $transaction->amount = 100;
        $transaction->fee = 0;
        $transaction->net = 100;
        $transaction->description = 'Payment';
        $transaction->available_on = CarbonImmutable::now();

        return $transaction;
    }

    protected function getExpectedToArray($model, array &$output): array
    {
        return [
            'amount' => 100.0,
            'available_on' => $model->available_on,
            'created_at' => $model->created_at,
            'currency' => 'usd',
            'description' => 'Payment',
            'fee' => 0.0,
            'fee_details' => [],
            'id' => $model->id,
            'merchant_account_id' => self::$merchantAccount->id,
            'net' => 100.0,
            'object' => 'merchant_account_transaction',
            'payout_id' => null,
            'reference' => $model->reference,
            'source_id' => null,
            'source_type' => null,
            'type' => 'payment',
            'updated_at' => $model->updated_at,
            'merchant_reference' => null,
        ];
    }

    protected function getModelEdit($model): MerchantAccountTransaction
    {
        $model->description = 'New description';

        return $model;
    }
}
