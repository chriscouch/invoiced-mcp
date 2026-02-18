<?php

namespace App\SalesTax\Models;

use App\AccountsReceivable\Models\AbstractRate;
use App\Core\I18n\Currencies;
use App\Core\Orm\Property;
use App\Core\Orm\Type;

/**
 * @property string      $name
 * @property bool        $is_percent
 * @property string|null $currency
 * @property float       $value
 * @property bool        $inclusive
 */
class TaxRate extends AbstractRate
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
            'inclusive' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
        ];
    }
}
