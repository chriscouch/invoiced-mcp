<?php

namespace App\Network\Models;

use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Member;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property int         $object_type
 * @property int         $object_id
 * @property Customer    $customer
 * @property Member|null $member
 */
class NetworkQueuedSend extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'object_type' => new Property(
                type: Type::INTEGER,
                required: true,
            ),
            'object_id' => new Property(
                type: Type::INTEGER,
                required: true,
            ),
            'customer' => new Property(
                required: true,
                belongs_to: Customer::class,
            ),
            'member' => new Property(
                null: true,
                belongs_to: Member::class,
            ),
        ];
    }
}
