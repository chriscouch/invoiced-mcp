<?php

namespace App\Core\Files\Models;

use App\AccountsPayable\Models\VendorPayment;
use App\Core\Orm\Property;

/**
 * Associates a file with a model.
 *
 * @property int           $vendor_payment_id
 * @property VendorPayment $vendor_payment
 */
class VendorPaymentAttachment extends AbstractAttachment
{
    protected static function getProperties(): array
    {
        return [
            'vendor_payment' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                in_array: false,
                belongs_to: VendorPayment::class,
            ),
            'file' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                in_array: false,
                belongs_to: File::class,
            ),
        ];
    }
}
