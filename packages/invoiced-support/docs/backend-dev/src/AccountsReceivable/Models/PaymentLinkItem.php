<?php

namespace App\AccountsReceivable\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property int         $id
 * @property PaymentLink $payment_link
 * @property string|null $description
 * @property float|null  $amount
 */
class PaymentLinkItem extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'payment_link' => new Property(
                belongs_to: PaymentLink::class,
            ),
            'description' => new Property(
                null: true,
            ),
            'amount' => new Property(
                type: Type::FLOAT,
                null: true,
                validate: ['range', 'min' => 0],
            ),
        ];
    }
}
