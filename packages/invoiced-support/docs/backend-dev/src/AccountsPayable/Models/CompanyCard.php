<?php

namespace App\AccountsPayable\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Traits\SoftDelete;
use App\Core\Orm\Type;

/**
 * @property int         $id
 * @property string      $funding
 * @property string      $brand
 * @property string      $last4
 * @property int         $exp_month
 * @property int         $exp_year
 * @property string|null $issuing_country
 * @property string|null $gateway
 * @property string|null $stripe_customer
 * @property string|null $stripe_payment_method
 */
class CompanyCard extends MultitenantModel
{
    use AutoTimestamps;
    use SoftDelete;

    protected static function getProperties(): array
    {
        return [
            'funding' => new Property(
                validate: ['enum', 'choices' => ['credit', 'debit', 'prepaid', 'unknown']],
                default: 'unknown',
            ),
            'brand' => new Property(
                required: true,
            ),
            'last4' => new Property(
                required: true,
            ),
            'exp_month' => new Property(
                type: Type::INTEGER,
                required: true,
            ),
            'exp_year' => new Property(
                type: Type::INTEGER,
                required: true,
            ),
            'issuing_country' => new Property(
                null: true,
            ),
            'gateway' => new Property(
                in_array: false,
            ),
            'stripe_customer' => new Property(
                null: true,
                in_array: false,
            ),
            'stripe_payment_method' => new Property(
                null: true,
                in_array: false,
            ),
        ];
    }
}
