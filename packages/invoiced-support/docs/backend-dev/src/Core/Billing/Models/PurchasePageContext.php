<?php

namespace App\Core\Billing\Models;

use App\Companies\Models\Company;
use App\Core\Billing\Enums\BillingPaymentTerms;
use App\Core\Billing\Enums\PurchasePageReason;
use App\Core\Utils\RandomString;
use DateTimeInterface;
use App\Core\Orm\Event\ModelCreating;
use App\Core\Orm\Model;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property string                 $identifier
 * @property PurchasePageReason     $reason
 * @property BillingProfile         $billing_profile
 * @property Company|null           $tenant
 * @property DateTimeInterface      $expiration_date
 * @property string|null            $note
 * @property string|null            $sales_rep
 * @property object                 $changeset
 * @property float|null             $activation_fee
 * @property BillingPaymentTerms    $payment_terms
 * @property string                 $country
 * @property bool                   $localized_pricing
 * @property DateTimeInterface|null $last_viewed
 * @property DateTimeInterface|null $completed_at
 * @property string|null            $completed_by_ip
 * @property string|null            $completed_by_name
 */
class PurchasePageContext extends Model
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'identifier' => new Property(),
            'billing_profile' => new Property(
                belongs_to: BillingProfile::class,
            ),
            'expiration_date' => new Property(
                type: Type::DATE,
            ),
            'reason' => new Property(
                type: Type::ENUM,
                enum_class: PurchasePageReason::class,
            ),
            'tenant' => new Property(
                null: true,
                belongs_to: Company::class,
            ),
            'sales_rep' => new Property(
                null: true,
            ),
            'country' => new Property(),
            'localized_pricing' => new Property(
                type: Type::BOOLEAN,
            ),
            'note' => new Property(
                null: true,
            ),
            'activation_fee' => new Property(
                type: Type::FLOAT,
                null: true,
            ),
            'payment_terms' => new Property(
                type: Type::ENUM,
                enum_class: BillingPaymentTerms::class,
            ),
            'changeset' => new Property(
                type: Type::OBJECT,
            ),
            'last_viewed' => new Property(
                type: Type::DATETIME,
                null: true,
            ),
            'completed_at' => new Property(
                type: Type::DATETIME,
                null: true,
            ),
            'completed_by_ip' => new Property(
                null: true,
            ),
            'completed_by_name' => new Property(
                null: true,
            ),
        ];
    }

    protected function initialize(): void
    {
        parent::initialize();
        self::creating(function (ModelCreating $event) {
            /** @var self $model */
            $model = $event->getModel();
            $model->identifier = RandomString::generate(48, RandomString::CHAR_ALNUM);
        });
    }
}
