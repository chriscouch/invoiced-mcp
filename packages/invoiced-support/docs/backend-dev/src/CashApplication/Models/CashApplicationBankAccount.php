<?php

namespace App\CashApplication\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Integrations\Plaid\Models\PlaidItem;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property int       $id
 * @property PlaidItem $plaid_link
 * @property int       $data_starts_at
 * @property int|null  $last_retrieved_data_at
 */
class CashApplicationBankAccount extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'plaid_link' => new Property(
                required: true,
                belongs_to: PlaidItem::class,
            ),
            'data_starts_at' => new Property(
                type: Type::DATE_UNIX,
                required: true,
            ),
            'last_retrieved_data_at' => new Property(
                type: Type::DATE_UNIX,
                null: true,
            ),
        ];
    }
}
