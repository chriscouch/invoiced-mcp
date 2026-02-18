<?php

namespace App\Integrations\Adyen\Models;

use App\CashApplication\Models\Payment;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use App\Integrations\Adyen\Enums\AdyenAffirmCaptureStatus;
use App\PaymentProcessing\Models\PaymentFlow;

/**
 * @property int                        $id
 * @property PaymentFlow                $payment_flow
 * @property ?Payment                   $payment
 * @property AdyenAffirmCaptureStatus   $status
 * @property ?string                    $reference
 * @property array                      $line_items
 */
class AdyenAffirmCapture extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'payment_flow' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                in_array: false,
                belongs_to: PaymentFlow::class,
            ),
            'payment' => new Property(
                validate: ['payment', ['unique', 'column' => 'payment_id']],
                default: null,
                in_array: false,
                belongs_to: Payment::class,
            ),
            'status' => new Property(
                type: Type::ENUM,
                default: AdyenAffirmCaptureStatus::Created,
                enum_class: AdyenAffirmCaptureStatus::class,
            ),
            'reference' => new Property(
                type: Type::STRING,
                default: null,
            ),
            'line_items' => new Property(
                type: Type::ARRAY,
                mutable: Property::MUTABLE_CREATE_ONLY,
                default: [],
                in_array: false,
            ),
        ];
    }
}
