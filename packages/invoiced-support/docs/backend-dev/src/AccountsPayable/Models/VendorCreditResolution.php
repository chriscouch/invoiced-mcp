<?php

namespace App\AccountsPayable\Models;

use App\Core\Orm\Property;

/**
 * @property VendorCredit $vendor_credit
 */
class VendorCreditResolution extends PayableDocumentResolution
{
    protected static function getProperties(): array
    {
        return array_merge(parent::getProperties(), [
            'vendor_credit' => new Property(
                required: true,
                belongs_to: VendorCredit::class,
            ),
        ]);
    }
}
