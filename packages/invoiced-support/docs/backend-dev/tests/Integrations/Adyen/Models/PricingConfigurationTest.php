<?php

namespace App\Tests\Integrations\Adyen\Models;

use App\Integrations\Adyen\Models\PricingConfiguration;
use App\Tests\ModelTestCase;

/**
 * @extends ModelTestCase<PricingConfiguration>
 */
class PricingConfigurationTest extends ModelTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::getService('test.database')->executeQuery('DELETE FROM PricingConfigurations WHERE hash="abc1234"');
    }

    protected function getModelCreate(): PricingConfiguration
    {
        return new PricingConfiguration([
            'merchant_account' => 'InvoicedCOM',
            'currency' => 'usd',
            'card_variable_fee' => 2.9,
            'chargeback_fee' => 15,
            'card_international_added_variable_fee' => 1,
            'hash' => 'abc1234',
        ]);
    }

    protected function getExpectedToArray($model, array &$output): array
    {
        return [
            'ach_fixed_fee' => null,
            'ach_max_fee' => null,
            'ach_variable_fee' => null,
            'amex_interchange_variable_markup' => null,
            'card_fixed_fee' => null,
            'card_interchange_passthrough' => null,
            'card_international_added_variable_fee' => 1.0,
            'card_variable_fee' => 2.9,
            'chargeback_fee' => 15.0,
            'created_at' => $model->created_at,
            'currency' => 'usd',
            'hash' => 'abc1234',
            'id' => $model->id,
            'merchant_account' => 'InvoicedCOM',
            'override_split_configuration_id' => null,
            'split_configuration_id' => null,
            'updated_at' => $model->updated_at,
        ];
    }

    protected function getModelEdit($model): PricingConfiguration
    {
        $model->split_configuration_id = '1234';

        return $model;
    }
}
