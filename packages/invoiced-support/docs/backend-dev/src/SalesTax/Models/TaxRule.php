<?php

namespace App\SalesTax\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\I18n\Countries;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;

/**
 * Model for tax rules.
 *
 * @property int         $id
 * @property string      $tax_rate
 * @property string|null $state
 * @property string|null $country
 */
class TaxRule extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'tax_rate' => new Property(
                required: true,
            ),
            'state' => new Property(
                null: true,
            ),
            'country' => new Property(
                null: true,
                validate: ['callable', 'fn' => [Countries::class, 'validateCountry']],
            ),
        ];
    }
}
