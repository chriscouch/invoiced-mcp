<?php

namespace App\AccountsPayable\Models;

use App\Core\Orm\Property;

/**
 * @property Bill $bill
 */
class BillResolution extends PayableDocumentResolution
{
    protected static function getProperties(): array
    {
        return array_merge(parent::getProperties(), [
            'bill' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                belongs_to: Bill::class,
            ),
        ]);
    }
}
