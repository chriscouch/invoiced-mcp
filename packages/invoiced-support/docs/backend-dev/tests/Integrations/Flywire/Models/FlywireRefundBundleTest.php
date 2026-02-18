<?php

namespace App\Tests\Integrations\Flywire\Models;

use App\Core\I18n\ValueObjects\Money;
use App\Core\Orm\Model;
use App\Integrations\Flywire\Models\FlywireRefundBundle;
use App\PaymentProcessing\Gateways\FlywireGateway;
use App\Tests\ModelTestCase;
use Carbon\CarbonImmutable;

/**
 * @extends ModelTestCase<FlywireRefundBundle>
 */
class FlywireRefundBundleTest extends ModelTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasMerchantAccount(FlywireGateway::ID);
    }

    protected function getModelCreate(): Model
    {
        $model = new FlywireRefundBundle();
        $model->bundle_id = 'ABC123';
        $model->recipient_id = 'UUO';
        $model->initiated_at = CarbonImmutable::now();
        $model->setAmount(new Money('USD', 100));

        return $model;
    }

    protected function getExpectedToArray($model, array &$output): array
    {
        return [
            'amount' => 1,
            'bundle_id' => 'ABC123',
            'created_at' => $model->created_at,
            'currency' => 'usd',
            'id' => $model->id,
            'initiated_at' => $model->initiated_at,
            'marked_for_approval' => null,
            'recipient_account_number' => null,
            'recipient_amount' => null,
            'recipient_bank_reference' => null,
            'recipient_currency' => null,
            'recipient_date' => null,
            'recipient_id' => 'UUO',
            'status' => null,
            'updated_at' => $model->updated_at,
        ];
    }

    protected function getModelEdit($model): FlywireRefundBundle
    {
        $model->bundle_id = 'ABC123UPDATE';

        return $model;
    }
}
