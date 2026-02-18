<?php

namespace App\AccountsReceivable\Models;

use App\Core\I18n\Currencies;
use App\Core\Orm\Property;
use App\Core\Orm\Type;

/**
 * @property string $name
 * @property bool   $is_percent
 * @property string $currency
 * @property float  $value
 * @property int    $expiration_date
 * @property int    $max_redemptions
 * @property bool   $exclusive
 * @property int    $duration
 */
class Coupon extends AbstractRate
{
    protected static function getProperties(): array
    {
        return [
            'name' => new Property(
                required: true,
                validate: ['callable', 'fn' => [self::class, 'validateName']],
            ),
            'is_percent' => new Property(
                type: Type::BOOLEAN,
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                default: true,
            ),
            'currency' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                null: true,
                validate: ['callable', 'fn' => [Currencies::class, 'validateCurrency'], 'nullable' => true],
            ),
            'value' => new Property(
                type: Type::FLOAT,
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                validate: 'numeric',
            ),
            'expiration_date' => new Property(
                type: Type::DATE_UNIX,
                null: true,
            ),
            'max_redemptions' => new Property(
                type: Type::INTEGER,
                validate: ['numeric', 'type' => 'integer'],
            ),
            'exclusive' => new Property(
                type: Type::BOOLEAN,
            ),
            'duration' => new Property(
                type: Type::INTEGER,
                validate: ['numeric', 'type' => 'integer'],
            ),
        ];
    }
}
