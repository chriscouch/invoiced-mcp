<?php

namespace App\Tests\Integrations\Flywire\Models;

use App\Core\I18n\ValueObjects\Money;
use App\Core\Orm\Model;
use App\Integrations\Flywire\Enums\FlywireRefundStatus;
use App\Integrations\Flywire\Models\FlywireRefund;
use App\Tests\ModelTestCase;
use Carbon\CarbonImmutable;

/**
 * @extends ModelTestCase<FlywireRefund>
 */
class FlywireRefundTest extends ModelTestCase
{
    protected function getModelCreate(): Model
    {
        $model = new FlywireRefund();
        $model->refund_id = 'ABC1234';
        $model->recipient_id = 'UUO';
        $model->initiated_at = CarbonImmutable::now();
        $model->setAmount(new Money('USD', 100));
        $model->setAmountTo(new Money('USD', 100));
        $model->status = FlywireRefundStatus::Initiated;

        return $model;
    }

    protected function getExpectedToArray($model, array &$output): array
    {
        return [
            'amount' => 1,
            'amount_to' => 1,
            'ar_refund_id' => null,
            'bundle_id' => null,
            'created_at' => $model->created_at,
            'currency' => 'usd',
            'currency_to' => 'usd',
            'id' => $model->id,
            'initiated_at' => $model->initiated_at,
            'payment_id' => null,
            'recipient_id' => 'UUO',
            'refund_id' => 'ABC1234',
            'status' => FlywireRefundStatus::Initiated->toString(),
            'updated_at' => $model->updated_at,
            'disbursement_id' => null,
        ];
    }

    protected function getModelEdit($model): FlywireRefund
    {
        $model->status = FlywireRefundStatus::Canceled;

        return $model;
    }
}
