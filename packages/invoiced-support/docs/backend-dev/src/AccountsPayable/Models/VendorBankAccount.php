<?php

namespace App\AccountsPayable\Models;

use App\Core\I18n\Currencies;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;

/**
 * @property int         $id
 * @property Vendor      $vendor
 * @property string      $last4
 * @property string      $bank_name
 * @property string|null $routing_number
 * @property string      $country
 * @property string      $currency
 * @property string|null $account_holder_name
 * @property string|null $account_holder_type
 * @property string|null $type
 * @property string      $account_number
 */
class VendorBankAccount extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'vendor' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                in_array: false,
                belongs_to: Vendor::class,
            ),
            'last4' => new Property(
                required: true,
            ),
            'bank_name' => new Property(
                required: true,
            ),
            'routing_number' => new Property(
                null: true,
            ),
            'country' => new Property(
                required: true,
                default: 'US',
            ),
            'currency' => new Property(
                required: true,
                validate: ['callable', 'fn' => [Currencies::class, 'validateCurrency']],
                default: 'usd',
            ),
            'account_holder_name' => new Property(
                null: true,
            ),
            'account_holder_type' => new Property(
                null: true,
                validate: ['enum', 'choices' => ['company', 'individual']]
            ),
            'type' => new Property(
                null: true,
                validate: ['enum', 'choices' => ['checking', 'savings']]
            ),
            'account_number' => new Property(
                required: true,
                encrypted: true,
            ),
        ];
    }
}
