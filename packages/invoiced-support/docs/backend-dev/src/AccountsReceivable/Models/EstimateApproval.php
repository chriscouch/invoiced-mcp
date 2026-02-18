<?php

namespace App\AccountsReceivable\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Type;

/**
 * @property int    $id
 * @property int    $estimate_id
 * @property int    $timestamp
 * @property string $user_agent
 * @property string $ip
 * @property string $initials
 */
class EstimateApproval extends MultitenantModel
{
    protected static function getProperties(): array
    {
        return [
            'estimate_id' => new Property(
                type: Type::INTEGER,
                required: true,
                in_array: false,
                relation: Estimate::class,
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
            'initials' => new Property(),
        ];
    }
}
