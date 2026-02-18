<?php

namespace App\Tests\Integrations\Flywire\Models;

use App\Core\I18n\ValueObjects\Money;
use App\Core\Orm\Model;
use App\Integrations\Flywire\Models\FlywireDisbursement;
use App\PaymentProcessing\Gateways\FlywireGateway;
use App\Tests\ModelTestCase;

/**
 * @extends ModelTestCase<FlywireDisbursement>
 */
class FlywireDisbursementTest extends ModelTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasMerchantAccount(FlywireGateway::ID);
    }

    protected function getModelCreate(): Model
    {
        $model = new FlywireDisbursement();
        $model->disbursement_id = 'ABC123';
        $model->setAmount(new Money('USD', 100));
        $model->bank_account_number = '123456789';
        $model->recipient_id = 'QQQ';
        $model->status_text = 'pending';

        return $model;
    }

    protected function getExpectedToArray($model, array &$output): array
    {
        return [
            'created_at' => $model->created_at,
            'disbursement_id' => 'ABC123',
            'id' => $model->id,
            'updated_at' => $model->updated_at,
            'amount' => 1,
            'bank_account_number' => '123456789',
            'currency' => 'usd',
            'recipient_id' => 'QQQ',
            'status_text' => 'pending',
            'delivered_at' => null,
        ];
    }

    protected function getModelEdit($model): FlywireDisbursement
    {
        $model->disbursement_id = 'ABC1234UPDATE';

        return $model;
    }
}
