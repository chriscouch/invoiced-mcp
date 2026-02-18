<?php

namespace App\PaymentProcessing\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;

/**
 * @property CustomerPaymentBatch $customer_payment_batch
 * @property Charge               $charge
 * @property int                  $charge_id
 */
class CustomerPaymentBatchItem extends MultitenantModel
{
    protected static function getProperties(): array
    {
        return [
            'customer_payment_batch' => new Property(
                null: true,
                belongs_to: CustomerPaymentBatch::class,
            ),
            'charge' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                null: true,
                belongs_to: Charge::class,
            ),
        ];
    }

    /**
     * Validates the value is not zero.
     */
    public static function notZero(mixed $value): bool
    {
        return 0 != $value;
    }
}
