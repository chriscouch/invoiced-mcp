<?php

namespace App\AccountsPayable\Models;

use App\Core\RestApi\Traits\ApiObjectTrait;
use App\Core\I18n\Currencies;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Event\ModelCreating;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use App\Core\Utils\ModelNormalizer;
use App\ActivityLog\Interfaces\EventObjectInterface;
use App\ActivityLog\Traits\EventModelTrait;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * @property int                    $id
 * @property Vendor                 $vendor
 * @property int                    $vendor_id
 * @property DateTimeInterface      $date
 * @property float                  $amount
 * @property string                 $currency
 * @property Bill|null              $bill
 * @property int|null               $bill_id
 * @property VendorCredit|null      $vendor_credit
 * @property int|null               $vendor_credit_id
 * @property string|null            $notes
 * @property bool                   $voided
 * @property DateTimeInterface|null $date_voided
 */
class VendorAdjustment extends MultitenantModel implements EventObjectInterface
{
    use ApiObjectTrait;
    use AutoTimestamps;
    use EventModelTrait;

    protected static function getProperties(): array
    {
        return [
            'vendor' => new Property(
                null: true,
                belongs_to: Vendor::class,
            ),
            'date' => new Property(
                type: Type::DATE,
            ),
            'amount' => new Property(
                type: Type::FLOAT,
                required: true,
                default: 0,
            ),
            'currency' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                validate: ['callable', 'fn' => [Currencies::class, 'validateCurrency']],
            ),
            'bill' => new Property(
                null: true,
                belongs_to: Bill::class,
            ),
            'vendor_credit' => new Property(
                null: true,
                belongs_to: VendorCredit::class,
            ),
            'notes' => new Property(
                type: Type::STRING,
                null: true,
            ),
            'voided' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'date_voided' => new Property(
                type: Type::DATE,
                null: true,
                in_array: false,
            ),
        ];
    }

    protected function initialize(): void
    {
        parent::initialize();
        self::creating([self::class, 'defaultValues']);
    }

    public static function defaultValues(ModelCreating $event): void
    {
        /** @var self $adjustment */
        $adjustment = $event->getModel();

        if (!$adjustment->date) { /* @phpstan-ignore-line */
            $adjustment->date = CarbonImmutable::now();
        }
    }

    public function getEventAssociations(): array
    {
        return [
            ['vendor', $this->vendor_id],
        ];
    }

    public function getEventObject(): array
    {
        return ModelNormalizer::toArray($this, expand: ['vendor']);
    }
}
