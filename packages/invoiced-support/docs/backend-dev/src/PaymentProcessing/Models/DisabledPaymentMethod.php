<?php

namespace App\PaymentProcessing\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;

/**
 * This model keeps track of disabled payment methods for various objects,
 * like customers and invoices.
 *
 * @property int    $id
 * @property string $object_type
 * @property string $object_id
 * @property string $method
 */
class DisabledPaymentMethod extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'object_type' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                validate: ['enum', 'choices' => ['customer', 'invoice', 'plan', 'estimate']],
            ),
            'object_id' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
            ),
            'method' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
            ),
        ];
    }
}
