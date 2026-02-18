<?php

namespace App\PaymentProcessing\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use DateTimeInterface;

/**
 * A merchant account transaction represents money movement within the client's merchant account.
 *
 * @property int                            $id
 * @property MerchantAccountTransaction     $merchant_account_transaction
 * @property DateTimeInterface              $notified_on
 */
class MerchantAccountTransactionNotification extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'merchant_account_transaction' => new Property(
                required: true,
                belongs_to: MerchantAccountTransaction::class,
            ),
            'notified_on' => new Property(
                type: Type::DATE,
                null: true,
            ),
        ];
    }
}