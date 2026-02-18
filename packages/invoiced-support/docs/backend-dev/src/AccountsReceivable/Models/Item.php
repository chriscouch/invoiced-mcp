<?php

namespace App\AccountsReceivable\Models;

use App\Core\I18n\Currencies;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Property;
use App\Core\Orm\Type;
use App\SalesTax\Models\TaxRate;

/**
 * @property int         $internal_id
 * @property string      $id
 * @property string|null $type
 * @property string      $name
 * @property string|null $currency
 * @property float       $unit_cost
 * @property string      $description
 * @property bool        $discountable
 * @property bool        $taxable
 * @property array       $taxes
 * @property string|null $avalara_tax_code
 * @property string|null $avalara_location_code
 * @property string|null $gl_account
 */
class Item extends PricingObject
{
    const BAD_DEBT = 'bad-debt';
    const BAD_DEBT_TYPE = 'bad_debt';

    //
    // Model Overrides
    //

    protected static function getProperties(): array
    {
        return [
            'type' => new Property(
                null: true,
            ),
            'name' => new Property(
                required: true,
            ),
            'currency' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                null: true,
                validate: ['callable', 'fn' => [Currencies::class, 'validateCurrency'], 'nullable' => true],
            ),
            'unit_cost' => new Property(
                type: Type::FLOAT,
                mutable: Property::MUTABLE_CREATE_ONLY,
                validate: 'numeric',
                default: 0,
            ),
            'description' => new Property(),
            'gl_account' => new Property(
                null: true,
                validate: ['callable', 'fn' => [GlAccount::class, 'validateCode']],
            ),
            'discountable' => new Property(
                type: Type::BOOLEAN,
                mutable: Property::MUTABLE_CREATE_ONLY,
                default: true,
            ),
            'taxable' => new Property(
                type: Type::BOOLEAN,
                mutable: Property::MUTABLE_CREATE_ONLY,
                default: true,
            ),
            'taxes' => new Property(
                type: Type::ARRAY,
                mutable: Property::MUTABLE_CREATE_ONLY,
                default: [],
            ),
            'avalara_tax_code' => new Property(
                null: true,
            ),
            'avalara_location_code' => new Property(
                null: true,
            ),
        ];
    }

    public function getTablename(): string
    {
        return 'CatalogItems';
    }

    public function toArray(): array
    {
        $result = parent::toArray();
        $result['object'] = $this->object;
        $result['metadata'] = $this->metadata;
        $result['taxes'] = TaxRate::expandList((array) $result['taxes']);

        return $result;
    }

    //
    // Hooks
    //

    public static function inheritCurrency(AbstractEvent $event): void
    {
        // inherit the company currency if a unit cost is supplied
        /** @var self $model */
        $model = $event->getModel();
        if (0 != $model->unit_cost && !$model->currency) {
            $model->currency = $model->tenant()->currency;
        }
    }

    //
    // Getters
    //

    /**
     * Generates a line item from this CatalogItem (minus quantity).
     */
    public function lineItem(): array
    {
        return [
            'type' => $this->type,
            'catalog_item' => $this->id,
            'catalog_item_id' => $this->internal_id,
            'name' => $this->name,
            'description' => $this->description,
            'unit_cost' => $this->unit_cost,
            'discountable' => $this->discountable,
            'taxable' => $this->taxable,
            'taxes' => $this->taxes,
        ];
    }
}
