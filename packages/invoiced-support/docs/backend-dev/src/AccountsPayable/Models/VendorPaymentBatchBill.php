<?php

namespace App\AccountsPayable\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Type;

/**
 * @property VendorPaymentBatch $vendor_payment_batch
 * @property Bill               $bill
 * @property int                $bill_id
 * @property string             $bill_number
 * @property Vendor             $vendor
 * @property int                $vendor_id
 * @property float              $amount
 * @property string|null        $error
 */
class VendorPaymentBatchBill extends MultitenantModel
{
    protected static function getProperties(): array
    {
        return [
            'vendor_payment_batch' => new Property(
                null: true,
                belongs_to: VendorPaymentBatch::class,
            ),
            'bill' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                null: true,
                belongs_to: Bill::class,
            ),
            'bill_number' => new Property(
                type: Type::STRING,
                mutable: Property::MUTABLE_CREATE_ONLY,
            ),
            'vendor' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                belongs_to: Vendor::class,
            ),
            'amount' => new Property(
                type: Type::FLOAT,
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                validate: ['callable', 'fn' => [self::class, 'notZero']],
            ),
            'error' => new Property(
                type: Type::STRING,
                null: true,
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
