<?php

namespace App\Integrations\Adyen\Models;

use App\Core\I18n\Currencies;
use App\Core\Orm\Model;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property int         $id
 * @property string      $merchant_account
 * @property string      $currency
 * @property float       $card_variable_fee
 * @property float       $card_international_added_variable_fee
 * @property float|null  $card_fixed_fee
 * @property bool        $card_interchange_passthrough
 * @property float|null  $amex_interchange_variable_markup
 * @property float|null  $ach_variable_fee
 * @property float|null  $ach_max_fee
 * @property float|null  $ach_fixed_fee
 * @property float       $chargeback_fee
 * @property string|null $override_split_configuration_id
 * @property string|null $split_configuration_id
 * @property string      $hash
 */
class PricingConfiguration extends Model
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'merchant_account' => new Property(
                required: true,
            ),
            'currency' => new Property(
                required: true,
                validate: ['callable', 'fn' => [Currencies::class, 'validateCurrency'], 'nullable' => true],
            ),
            'card_variable_fee' => new Property(
                type: Type::FLOAT,
            ),
            'card_international_added_variable_fee' => new Property(
                type: Type::FLOAT,
            ),
            'card_fixed_fee' => new Property(
                type: Type::FLOAT,
                null: true,
            ),
            'card_interchange_passthrough' => new Property(
                type: Type::BOOLEAN,
            ),
            'amex_interchange_variable_markup' => new Property(
                type: Type::FLOAT,
                null: true,
            ),
            'ach_variable_fee' => new Property(
                type: Type::FLOAT,
                null: true,
            ),
            'ach_max_fee' => new Property(
                type: Type::FLOAT,
                null: true,
            ),
            'ach_fixed_fee' => new Property(
                type: Type::FLOAT,
                null: true,
            ),
            'chargeback_fee' => new Property(
                type: Type::FLOAT,
            ),
            'override_split_configuration_id' => new Property(
                null: true,
            ),
            'split_configuration_id' => new Property(
                null: true,
            ),
            'hash' => new Property(
                required: true,
            ),
        ];
    }
}
