<?php

namespace App\Integrations\AccountingSync\Models;

use App\AccountsReceivable\Models\Item;
use App\Core\Orm\Property;
use App\Integrations\Enums\IntegrationType;

/**
 * @property Item $item
 * @property int  $item_id
 */
class AccountingItemMapping extends AbstractMapping
{
    protected static function getIDProperties(): array
    {
        return ['item_id'];
    }

    protected static function getProperties(): array
    {
        return [
            'item' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                foreign_key: 'internal_id',
                belongs_to: Item::class,
            ),
            'accounting_id' => new Property(
                required: true,
            ),
            'source' => new Property(
                required: true,
                validate: ['enum', 'choices' => ['accounting_system', 'invoiced']],
            ),
        ];
    }

    public static function findForItem(Item $item, IntegrationType $integration): ?self
    {
        return self::where('integration_id', $integration->value)
            ->where('item_id', $item)
            ->oneOrNull();
    }
}
