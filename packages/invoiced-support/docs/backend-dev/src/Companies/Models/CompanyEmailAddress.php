<?php

namespace App\Companies\Models;

use App\Companies\Enums\VerificationStatus;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Utils\RandomString;
use DateTimeInterface;
use App\Core\Orm\Event\ModelCreating;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property string                 $email
 * @property string                 $token
 * @property string                 $code
 * @property DateTimeInterface|null $verified_at
 */
class CompanyEmailAddress extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'email' => new Property(
                required: true,
            ),
            'token' => new Property(
                required: true,
            ),
            'code' => new Property(
                required: true,
            ),
            'verified_at' => new Property(
                type: Type::DATETIME,
                null: true,
            ),
        ];
    }

    protected function initialize(): void
    {
        parent::initialize();
        self::creating([self::class, 'generateCodes']);
    }

    public static function generateCodes(ModelCreating $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        if (!$model->token) {
            $model->token = RandomString::generate(24, RandomString::CHAR_ALNUM);
        }

        if (!$model->code) {
            $model->code = RandomString::generate(6, RandomString::CHAR_NUMERIC);
        }
    }

    public static function getVerificationStatus(Company $company): VerificationStatus
    {
        $email = $company->email;
        if (!$email) {
            return VerificationStatus::NotVerified;
        }

        $companyEmail = self::queryWithTenant($company)
            ->where('email', $email)
            ->oneOrNull();
        if (!$companyEmail) {
            return VerificationStatus::NotVerified;
        }

        return $companyEmail->verified_at ? VerificationStatus::Verified : VerificationStatus::Pending;
    }
}
