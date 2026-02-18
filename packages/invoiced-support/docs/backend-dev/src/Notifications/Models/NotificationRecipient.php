<?php

namespace App\Notifications\Models;

use App\Companies\Models\Member;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Type;

/**
 * Represents a notification recepient relation.
 *
 * @property int               $id
 * @property int               $notification_event_id
 * @property int               $member_id
 * @property Member            $member
 * @property NotificationEvent $notification_event
 * @property bool              $sent
 */
class NotificationRecipient extends MultitenantModel
{
    protected static function getProperties(): array
    {
        return [
            'notification_event' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                belongs_to: NotificationEvent::class,
            ),
            'member' => new Property(
                type: Type::OBJECT,
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                belongs_to: Member::class,
            ),
            'sent' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
        ];
    }
}
