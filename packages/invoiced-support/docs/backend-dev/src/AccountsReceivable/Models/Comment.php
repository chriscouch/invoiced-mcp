<?php

namespace App\AccountsReceivable\Models;

use App\Core\Authentication\Models\User;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use App\Core\RestApi\Traits\ApiObjectTrait;
use App\ActivityLog\Interfaces\EventObjectInterface;
use App\ActivityLog\Traits\EventObjectTrait;

/**
 * Represents a comment on a document left by a merchant or customer.
 *
 * @property int    $id
 * @property string $parent_type
 * @property string $parent_id
 * @property string $text
 * @property bool   $from_customer
 * @property int    $user_id
 */
class Comment extends MultitenantModel implements EventObjectInterface
{
    use ApiObjectTrait;
    use AutoTimestamps;
    use EventObjectTrait;

    protected static function getProperties(): array
    {
        return [
            'parent_type' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                validate: ['enum', 'choices' => ['credit_note', 'estimate', 'invoice']],
                in_array: false,
            ),
            'parent_id' => new Property(
                type: Type::INTEGER,
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                in_array: false,
            ),
            'text' => new Property(
                required: true,
            ),
            'from_customer' => new Property(
                type: Type::BOOLEAN,
            ),
            'user_id' => new Property(
                type: Type::INTEGER,
                mutable: Property::MUTABLE_CREATE_ONLY,
                null: true,
                in_array: false,
                relation: User::class,
            ),
        ];
    }
}
