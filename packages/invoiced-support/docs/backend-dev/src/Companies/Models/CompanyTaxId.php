<?php

namespace App\Companies\Models;

use App\Companies\Enums\TaxIdType;
use App\Companies\Enums\VerificationStatus;
use App\Core\Multitenant\Models\MultitenantModel;
use DateTimeInterface;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property string                 $name
 * @property string                 $country
 * @property string                 $tax_id
 * @property TaxIdType              $tax_id_type
 * @property int|null               $irs_code
 * @property DateTimeInterface|null $verified_at
 */
class CompanyTaxId extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'name' => new Property(
                required: true,
            ),
            'country' => new Property(
                required: true,
            ),
            'tax_id' => new Property(
                required: true,
                encrypted: true,
            ),
            'tax_id_type' => new Property(
                type: Type::ENUM,
                required: true,
                enum_class: TaxIdType::class,
            ),
            'irs_code' => new Property(
                type: Type::INTEGER,
                null: true,
            ),
            'verified_at' => new Property(
                type: Type::DATETIME,
                null: true,
            ),
        ];
    }

    public static function getVerificationStatus(Company $company): VerificationStatus
    {
        if (!$company->country) {
            return VerificationStatus::NotVerified;
        }

        $companyTaxId = self::queryWithTenant($company)
            ->where('country', $company->country)
            ->oneOrNull();
        if (!$companyTaxId) {
            return VerificationStatus::NotVerified;
        }

        return $companyTaxId->verified_at ? VerificationStatus::Verified : VerificationStatus::Pending;
    }
}
