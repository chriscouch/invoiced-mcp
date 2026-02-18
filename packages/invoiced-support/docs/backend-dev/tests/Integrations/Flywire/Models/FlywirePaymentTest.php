<?php

namespace App\Tests\Integrations\Flywire\Models;

use App\Core\I18n\ValueObjects\Money;
use App\Core\Orm\Model;
use App\Integrations\Flywire\Enums\FlywirePaymentStatus;
use App\Integrations\Flywire\Models\FlywirePayment;
use App\PaymentProcessing\Gateways\FlywireGateway;
use App\Tests\ModelTestCase;
use Carbon\CarbonImmutable;

/**
 * @extends ModelTestCase<FlywirePayment>
 */
class FlywirePaymentTest extends ModelTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasMerchantAccount(FlywireGateway::ID);
    }

    protected function getModelCreate(): Model
    {
        $model = new FlywirePayment();
        $model->merchant_account = self::$merchantAccount;
        $model->payment_id = 'ABC123';
        $model->recipient_id = 'UUO';
        $model->initiated_at = CarbonImmutable::now();
        $model->setAmountFrom(new Money('USD', 100));
        $model->setAmountTo(new Money('USD', 100));
        $model->status = FlywirePaymentStatus::Initiated;

        return $model;
    }

    protected function getExpectedToArray($model, array &$output): array
    {
        return [
            'amount_from' => 1,
            'amount_to' => 1,
            'ar_payment_id' => null,
            'cancellation_reason' => null,
            'created_at' => $model->created_at,
            'currency_from' => 'usd',
            'currency_to' => 'usd',
            'expiration_date' => null,
            'id' => $model->id,
            'initiated_at' => $model->initiated_at,
            'merchant_account_id' => self::$merchantAccount->id,
            'payment_id' => 'ABC123',
            'payment_method_brand' => null,
            'payment_method_card_classification' => null,
            'payment_method_card_expiration' => null,
            'payment_method_last4' => null,
            'payment_method_type' => null,
            'reason' => null,
            'reason_code' => null,
            'recipient_id' => 'UUO',
            'status' => 'initiated',
            'updated_at' => $model->updated_at,
            'reference' => null,
            'surcharge_percentage' => 0.0,
        ];
    }

    protected function getModelEdit($model): FlywirePayment
    {
        $model->status = FlywirePaymentStatus::Canceled;

        return $model;
    }
}
