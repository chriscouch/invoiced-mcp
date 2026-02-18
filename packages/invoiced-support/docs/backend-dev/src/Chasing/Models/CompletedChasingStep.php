<?php

namespace App\Chasing\Models;

use App\AccountsReceivable\Models\Customer;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Type;

/**
 * @property int         $customer_id
 * @property int         $cadence_id
 * @property int         $chase_step_id
 * @property int         $timestamp
 * @property bool        $successful
 * @property string|null $message
 */
class CompletedChasingStep extends MultitenantModel
{
    protected static function getProperties(): array
    {
        return [
            'customer_id' => new Property(
                type: Type::INTEGER,
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                relation: Customer::class,
            ),
            'cadence_id' => new Property(
                type: Type::INTEGER,
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                relation: ChasingCadence::class,
            ),
            'chase_step_id' => new Property(
                type: Type::INTEGER,
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                relation: ChasingCadenceStep::class,
            ),
            'timestamp' => new Property(
                type: Type::DATE_UNIX,
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
            ),
            'successful' => new Property(
                type: Type::BOOLEAN,
                required: true,
            ),
            'message' => new Property(
                null: true,
            ),
        ];
    }
}
