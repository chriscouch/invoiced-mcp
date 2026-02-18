<?php

namespace App\Core\Billing\Models;

use App\Companies\Models\Company;
use App\Core\Billing\Enums\UsageType;
use App\Core\Orm\Model;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property int|null            $billing_profile_id
 * @property BillingProfile|null $billing_profile
 * @property int|null            $tenant_id
 * @property Company|null        $tenant
 * @property UsageType           $usage_type
 * @property int                 $threshold
 * @property float               $unit_price
 */
class UsagePricingPlan extends Model
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'billing_profile' => new Property(
                null: true,
                belongs_to: BillingProfile::class,
            ),
            'tenant' => new Property(
                null: true,
                belongs_to: Company::class,
            ),
            'usage_type' => new Property(
                type: Type::ENUM,
                required: true,
                enum_class: UsageType::class,
            ),
            'threshold' => new Property(
                type: Type::INTEGER,
                required: true,
            ),
            'unit_price' => new Property(
                type: Type::FLOAT,
                required: true,
            ),
        ];
    }
}
