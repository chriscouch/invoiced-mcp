<?php

namespace App\PaymentPlans\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Type;

/**
 * This records a customer's consent to a given payment plan.
 *
 * @property int    $id
 * @property int    $payment_plan_id
 * @property int    $timestamp
 * @property string $user_agent
 * @property string $ip
 */
class PaymentPlanApproval extends MultitenantModel
{
    protected static function getProperties(): array
    {
        return [
            'payment_plan_id' => new Property(
                type: Type::INTEGER,
                required: true,
                in_array: false,
                relation: PaymentPlan::class,
            ),
            'timestamp' => new Property(
                type: Type::DATE_UNIX,
                required: true,
                validate: 'timestamp',
                default: 'now',
            ),
            'user_agent' => new Property(
                required: true,
            ),
            'ip' => new Property(
                required: true,
                validate: 'ip',
            ),
        ];
    }
}
