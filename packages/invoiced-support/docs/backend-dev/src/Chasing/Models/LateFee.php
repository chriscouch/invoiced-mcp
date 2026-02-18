<?php

namespace App\Chasing\Models;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\LineItem;
use App\Core\Multitenant\Models\MultitenantModel;
use DateTimeInterface;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * Associates a file that is attached to a document.
 *
 * @property int                $id
 * @property int                $customer_id
 * @property int                $invoice_id
 * @property int                $line_item_id
 * @property ?DateTimeInterface $date
 * @property int                $version
 */
class LateFee extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'customer_id' => new Property(
                type: Type::INTEGER,
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                relation: Customer::class,
            ),
            'invoice_id' => new Property(
                type: Type::INTEGER,
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                relation: Invoice::class,
            ),
            'line_item_id' => new Property(
                type: Type::INTEGER,
                null: true,
                relation: LineItem::class,
            ),
            'date' => new Property(
                type: Type::DATE,
                mutable: Property::MUTABLE_CREATE_ONLY,
                in_array: false,
            ),
            'version' => new Property(
                type: Type::INTEGER,
                mutable: Property::MUTABLE_CREATE_ONLY,
                default: 2,
                in_array: false,
            ),
        ];
    }
}
