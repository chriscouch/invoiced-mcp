<?php

namespace App\Core\Billing\Models;

use App\Companies\Models\Company;
use App\Core\Billing\Enums\BillingInterval;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Model;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property int                  $id
 * @property string               $name
 * @property string|null          $billing_system
 * @property string|null          $invoiced_customer
 * @property string|null          $stripe_customer
 * @property bool                 $past_due
 * @property string               $referred_by
 * @property BillingInterval|null $billing_interval
 */
class BillingProfile extends Model
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'name' => new Property(
                required: true,
            ),
            'billing_system' => new Property(
                null: true,
                validate: ['enum', 'choices' => ['invoiced', 'reseller', 'stripe']],
                in_array: false,
            ),
            'invoiced_customer' => new Property(
                null: true,
                in_array: false,
            ),
            'stripe_customer' => new Property(
                null: true,
                in_array: false,
            ),
            'past_due' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'referred_by' => new Property(
                in_array: false,
            ),
            'billing_interval' => new Property(
                type: Type::ENUM,
                null: true,
                in_array: false,
                enum_class: BillingInterval::class,
            ),
        ];
    }

    protected function initialize(): void
    {
        parent::initialize();
        self::deleting(function (): never {
            throw new ListenerException('Deleting billing profiles not permitted');
        });
    }

    public static function getOrCreate(Company $company): self
    {
        if ($billingProfile = $company->billing_profile) {
            return $billingProfile;
        }

        $billingProfile = new BillingProfile();
        $billingProfile->name = $company->name;
        $billingProfile->saveOrFail();

        $company->billing_profile = $billingProfile;
        $company->saveOrFail();

        return $billingProfile;
    }
}
