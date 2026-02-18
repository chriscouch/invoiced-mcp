<?php

namespace App\Companies\Models;

use App\Core\Authentication\Models\User;
use App\Core\Billing\Models\BillingProfile;
use App\Core\Orm\Model;
use App\Core\Orm\Property;
use App\Core\Orm\Type;

/**
 * @property int         $id
 * @property string      $name
 * @property string|null $email
 * @property string      $username
 * @property string|null $custom_domain
 * @property string|null $industry
 * @property string      $type
 * @property string|null $address1
 * @property string|null $address2
 * @property string|null $city
 * @property string|null $state
 * @property string|null $postal_code
 * @property string|null $country
 * @property string|null $tax_id
 * @property string      $address_extra
 * @property int|null    $creator_id
 * @property string      $stripe_customer
 * @property bool        $past_due
 * @property int|null    $canceled_at
 * @property int|null    $trial_started
 * @property int|null    $converted_at
 * @property string      $converted_from
 * @property string      $canceled_reason
 * @property string      $referred_by
 */
class CanceledCompany extends Model
{
    protected static function getProperties(): array
    {
        return [
            'id' => new Property(
                type: Type::INTEGER,
            ),
            'name' => new Property(),
            'email' => new Property(),
            'industry' => new Property(
                null: true,
            ),
            'username' => new Property(),
            'custom_domain' => new Property(
                null: true,
            ),
            'type' => new Property(
                null: true,
            ),
            'address1' => new Property(
                null: true,
            ),
            'address2' => new Property(
                null: true,
            ),
            'city' => new Property(
                null: true,
            ),
            'state' => new Property(
                null: true,
            ),
            'postal_code' => new Property(
                null: true,
            ),
            'country' => new Property(
                null: true,
            ),
            'tax_id' => new Property(
                null: true,
            ),
            'address_extra' => new Property(),

            /* Setup process */

            'creator_id' => new Property(
                type: Type::INTEGER,
                mutable: Property::MUTABLE_CREATE_ONLY,
                null: true,
                relation: User::class,
            ),

            /* Billing */

            'stripe_customer' => new Property(),
            'invoiced_customer' => new Property(),
            'past_due' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'canceled_at' => new Property(
                type: Type::DATE_UNIX,
                null: true,
            ),
            'billing_profile' => new Property(
                null: true,
                in_array: false,
                belongs_to: BillingProfile::class,
            ),

            /* Conversion Tracking */

            'trial_started' => new Property(
                type: Type::DATE_UNIX,
                null: true,
            ),
            'converted_at' => new Property(
                type: Type::DATE_UNIX,
                null: true,
            ),
            'converted_from' => new Property(),
            'canceled_reason' => new Property(),
            'referred_by' => new Property(),

            /* Timestamps */

            'created_at' => new Property(
                type: Type::DATE_UNIX,
                validate: ['timestamp', 'db_timestamp'],
            ),
            'updated_at' => new Property(
                type: Type::DATE_UNIX,
                validate: ['timestamp', 'db_timestamp'],
            ),
        ];
    }
}
