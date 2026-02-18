<?php

namespace App\Integrations\Adyen\Models;

use App\Core\Authentication\Models\User;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use DateTimeInterface;

/**
 * @property int                       $id
 * @property string|null               $industry_code
 * @property DateTimeInterface|null    $terms_of_service_acceptance_date
 * @property string|null               $terms_of_service_acceptance_ip
 * @property string|null               $terms_of_service_acceptance_version
 * @property User|null                 $terms_of_service_acceptance_user
 * @property string|null               $account_holder_id
 * @property string|null               $legal_entity_id
 * @property string|null               $business_line_id
 * @property string|null               $reference
 * @property PricingConfiguration|null $pricing_configuration
 * @property string|null               $balance_account_id
 * @property string|null               $store_id
 * @property DateTimeInterface|null    $onboarding_started_at
 * @property DateTimeInterface|null    $activated_at
 * @property DateTimeInterface|null    $last_onboarding_reminder_sent
 * @property bool                      $has_onboarding_problem
 * @property string|null               $statement_descriptor
 */
class AdyenAccount extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'industry_code' => new Property(
                null: true,
            ),
            'terms_of_service_acceptance_date' => new Property(
                type: Type::DATETIME,
                null: true,
            ),
            'terms_of_service_acceptance_ip' => new Property(
                null: true,
            ),
            'terms_of_service_acceptance_version' => new Property(
                null: true,
            ),
            'terms_of_service_acceptance_user' => new Property(
                null: true,
                belongs_to: User::class,
            ),
            'legal_entity_id' => new Property(
                null: true,
            ),
            'business_line_id' => new Property(
                null: true,
            ),
            'store_id' => new Property(
                null: true,
            ),
            'reference' => new Property(
                null: true,
            ),
            'account_holder_id' => new Property(
                null: true,
            ),
            'balance_account_id' => new Property(
                null: true,
            ),
            'pricing_configuration' => new Property(
                null: true,
                belongs_to: PricingConfiguration::class,
            ),
            'onboarding_started_at' => new Property(
                type: Type::DATETIME,
                null: true,
            ),
            'activated_at' => new Property(
                type: Type::DATETIME,
                null: true,
            ),
            'last_onboarding_reminder_sent' => new Property(
                type: Type::DATE,
                null: true,
            ),
            'has_onboarding_problem' => new Property(
                type: Type::BOOLEAN,
            ),
            'statement_descriptor' => new Property(
                null: true,
            ),
        ];
    }

    public function getStatementDescriptor(): string
    {
        $descriptor = $this->statement_descriptor ?: $this->tenant()->getDisplayName();

        return substr($descriptor, 0, 22);
    }
}
