<?php

namespace App\Companies\Models;

use App\Companies\Enums\VerificationStatus;
use App\Core\Multitenant\Models\MultitenantModel;
use DateTimeInterface;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property string                 $address1
 * @property string|null            $address2
 * @property string                 $city
 * @property string                 $state
 * @property string|null            $postal_code
 * @property string                 $country
 * @property DateTimeInterface|null $verified_at
 */
class CompanyAddress extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'address1' => new Property(
                required: true,
            ),
            'address2' => new Property(
                null: true,
            ),
            'city' => new Property(
                required: true,
            ),
            'state' => new Property(
                required: true,
            ),
            'postal_code' => new Property(
                null: true,
            ),
            'country' => new Property(
                required: true,
            ),
            'verified_at' => new Property(
                type: Type::DATETIME,
                null: true,
            ),
        ];
    }

    public static function getVerificationStatus(Company $company): VerificationStatus
    {
        if (!$company->address1 || !$company->country) {
            return VerificationStatus::NotVerified;
        }

        $companyAddress = self::queryWithTenant($company)
            ->where('address1', $company->address1)
            ->where('address2', $company->address2)
            ->where('city', $company->city)
            ->where('state', $company->state)
            ->where('postal_code', $company->postal_code)
            ->where('country', $company->country)
            ->oneOrNull();
        if (!$companyAddress) {
            return VerificationStatus::NotVerified;
        }

        return $companyAddress->verified_at ? VerificationStatus::Verified : VerificationStatus::Pending;
    }
}
