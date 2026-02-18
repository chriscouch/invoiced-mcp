<?php

namespace App\PaymentProcessing\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Type;

/**
 * @property int             $id
 * @property InitiatedCharge $initiated_charge
 * @property int             $initiated_charge_id
 * @property int             $document_type
 * @property ?int            $document_id
 * @property float           $amount
 */
class InitiatedChargeDocument extends MultitenantModel
{
    protected static function getProperties(): array
    {
        return [
            'initiated_charge' => new Property(
                required: true,
                belongs_to: InitiatedCharge::class,
            ),
            // type of charge application
            'document_type' => new Property(
                required: true,
            ),
            'document_id' => new Property(
                null: true,
            ),
            'amount' => new Property(
                type: Type::FLOAT,
                required: true,
            ),
        ];
    }
}
