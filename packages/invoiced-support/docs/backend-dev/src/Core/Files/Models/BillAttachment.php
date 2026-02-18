<?php

namespace App\Core\Files\Models;

use App\AccountsPayable\Models\Bill;
use App\Core\Orm\Property;

/**
 * Associates a file with a model.
 *
 * @property int  $bill_id
 * @property Bill $bill
 */
class BillAttachment extends AbstractAttachment
{
    protected static function getProperties(): array
    {
        return [
            'bill' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                in_array: false,
                belongs_to: Bill::class,
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
