<?php

namespace App\AccountsPayable\Models;

use App\AccountsPayable\Enums\VendorPaymentItemTypes;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property int                    $id
 * @property VendorPayment          $vendor_payment
 * @property float                  $amount
 * @property Bill|null              $bill
 * @property int|null               $bill_id
 * @property VendorCredit|null      $vendor_credit
 * @property int|null               $vendor_credit_id
 * @property VendorPaymentItemTypes $type
 */
class VendorPaymentItem extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'vendor_payment' => new Property(
                required: true,
                belongs_to: VendorPayment::class,
            ),
            'bill' => new Property(
                null: true,
                belongs_to: Bill::class,
            ),
            'vendor_credit' => new Property(
                null: true,
                belongs_to: VendorCredit::class,
            ),
            'amount' => new Property(
                type: Type::FLOAT,
                required: true,
                default: 0,
            ),
            'type' => new Property(
                type: Type::ENUM,
                required: true,
                default: VendorPaymentItemTypes::Application,
                enum_class: VendorPaymentItemTypes::class,
            ),
        ];
    }
}
