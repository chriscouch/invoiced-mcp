<?php

namespace App\SubscriptionBilling\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Type;

/**
 * This keeps track of customer consent on subscriptions.
 * Approval would be captured whenever the customer purchases
 * a subscription online, versus when a merchant creates the subscription.
 *
 * @property int    $id
 * @property int    $subscription_id
 * @property int    $timestamp
 * @property string $user_agent
 * @property string $ip
 */
class SubscriptionApproval extends MultitenantModel
{
    protected static function getProperties(): array
    {
        return [
            'subscription_id' => new Property(
                type: Type::INTEGER,
                required: true,
                in_array: false,
                relation: Subscription::class,
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
