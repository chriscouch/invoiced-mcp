<?php

namespace App\Core\Files\Models;

use App\AccountsPayable\Models\VendorCredit;
use App\Core\Orm\Property;

/**
 * Associates a file with a model.
 *
 * @property int          $vendor_credit_id
 * @property VendorCredit $vendor_credit
 */
class VendorCreditAttachment extends AbstractAttachment
{
    protected static function getProperties(): array
    {
        return [
            'vendor_credit' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                in_array: false,
                belongs_to: VendorCredit::class,
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
