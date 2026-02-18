<?php

namespace App\AccountsReceivable\Models;

use App\Core\I18n\Currencies;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property int    $internal_id
 * @property string $id
 * @property string $name
 * @property string $currency
 * @property array  $items
 */
class Bundle extends MultitenantModel
{
    use AutoTimestamps;

    const MAX_ITEMS = 100;

    //
    // Model Overrides
    //

    protected static function getIDProperties(): array
    {
        return ['internal_id'];
    }

    protected static function getProperties(): array
    {
        return [
            'internal_id' => new Property(
                type: Type::INTEGER,
                mutable: Property::IMMUTABLE,
                in_array: false,
            ),
            'id' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                validate: [
                    ['callable', 'fn' => [self::class, 'validateID']],
                    ['unique', 'column' => 'id'],
                ],
            ),
            'name' => new Property(
                required: true,
            ),
            'currency' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                validate: ['callable', 'fn' => [Currencies::class, 'validateCurrency']],
            ),
            'items' => new Property(
                type: Type::ARRAY,
                validate: ['callable', 'fn' => [self::class, 'validateItems']],
                default: [],
            ),
        ];
    }

    protected function initialize(): void
    {
        parent::initialize();
        self::creating([static::class, 'inheritCurrency']);
    }

    public function toArray(): array
    {
        $result = parent::toArray();

        $items = [];
        foreach ($result['items'] as $item) {
            $catalogItem = Item::getCurrent($item['catalog_item']);
            if (!$catalogItem) {
                continue;
            }

            $items[] = [
                'catalog_item' => $catalogItem->toArray(),
                'quantity' => $item['quantity'],
            ];
        }
        $result['items'] = $items;

        return $result;
    }

    /**
     * Inherits the currency from the company, if not specified.
     */
    public static function inheritCurrency(AbstractEvent $event): void
    {
        // fall back to company currency if none given
        /** @var self $model */
        $model = $event->getModel();
        if (!$model->currency) {
            $model->currency = $model->tenant()->currency;
        }
    }

    //
    // Mutators
    //

    protected function setCurrencyValue(string $currency): string
    {
        return strtolower($currency);
    }

    //
    // Validators
    //

    /**
     * Validates the external, user-supplied ID.
     */
    public static function validateID(mixed $id): bool
    {
        if (!is_string($id)) {
            return false;
        }

        // Allowed characters: a-z, A-Z, 0-9, _, -
        // Min length: 2
        return preg_match('/^[a-z0-9_-]{2,}$/i', $id) > 0;
    }

    /**
     * Validates the bundle items.
     */
    public static function validateItems(mixed $items): bool
    {
        if (!is_array($items)) {
            return false;
        }

        if (0 === count($items) || count($items) > self::MAX_ITEMS) {
            return false;
        }

        foreach ($items as $item) {
            if (!isset($item['catalog_item'])) {
                return false;
            }

            if (!isset($item['quantity']) || !is_integer($item['quantity'])) {
                return false;
            }
        }

        return true;
    }
}
