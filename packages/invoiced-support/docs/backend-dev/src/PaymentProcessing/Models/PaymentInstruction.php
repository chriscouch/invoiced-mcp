<?php

namespace App\PaymentProcessing\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Type;

/**
 * This model represents a payment method overrides that the merchant accepts.
 *
 * @property int    $id
 * @property string $payment_method_id
 * @property string $country
 * @property string $meta
 * @property bool   $enabled
 */
class PaymentInstruction extends MultitenantModel
{
    protected static function getProperties(): array
    {
        return [
            'payment_method_id' => new Property(
                required: true,
            ),
            'country' => new Property(
                required: true,
            ),
            'meta' => new Property(
                null: true,
            ),
            'enabled' => new Property(
                type: Type::BOOLEAN,
                validate: 'boolean',
            ),
        ];
    }
}
