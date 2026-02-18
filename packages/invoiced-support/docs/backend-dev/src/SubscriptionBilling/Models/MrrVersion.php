<?php

namespace App\SubscriptionBilling\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use DateTimeInterface;
use App\Core\Orm\Property;
use App\Core\Orm\Type;

/**
 * @property int                    $id
 * @property string                 $currency
 * @property DateTimeInterface|null $last_updated
 */
class MrrVersion extends MultitenantModel
{
    protected static function getProperties(): array
    {
        return [
            'currency' => new Property(),
            'last_updated' => new Property(
                type: Type::DATETIME,
                null: true,
            ),
        ];
    }
}
