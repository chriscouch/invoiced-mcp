<?php

namespace App\Companies\Models;

use App\Companies\Enums\PhoneVerificationChannel;
use App\Companies\Enums\VerificationStatus;
use App\Core\Multitenant\Models\MultitenantModel;
use DateTimeInterface;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property string                   $phone
 * @property PhoneVerificationChannel $channel
 * @property DateTimeInterface|null   $verified_at
 */
class CompanyPhoneNumber extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'phone' => new Property(
                required: true,
            ),
            'channel' => new Property(
                type: Type::ENUM,
                required: true,
                enum_class: PhoneVerificationChannel::class,
            ),
            'verified_at' => new Property(
                type: Type::DATETIME,
                null: true,
            ),
        ];
    }

    public static function getVerificationStatus(Company $company): VerificationStatus
    {
        $companyPhone = self::queryWithTenant($company)
            ->sort('id DESC')
            ->oneOrNull();
        if (!$companyPhone) {
            return VerificationStatus::NotVerified;
        }

        return $companyPhone->verified_at ? VerificationStatus::Verified : VerificationStatus::Pending;
    }
}
