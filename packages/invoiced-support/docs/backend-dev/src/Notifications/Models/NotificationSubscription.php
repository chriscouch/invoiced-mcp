<?php

namespace App\Notifications\Models;

use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Member;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Type;

/**
 * Represents a notification customer subscription.
 *
 * @property int      $customer_id
 * @property int      $member_id
 * @property Customer $customer
 * @property Member   $member
 * @property bool     $subscribe
 */
class NotificationSubscription extends MultitenantModel
{
    protected static function getProperties(): array
    {
        return [
            'customer' => new Property(
                required: true,
                belongs_to: Customer::class,
            ),
            'member' => new Property(
                required: true,
                belongs_to: Member::class,
            ),
            'subscribe' => new Property(
                type: Type::BOOLEAN,
                required: true,
            ),
        ];
    }
}
