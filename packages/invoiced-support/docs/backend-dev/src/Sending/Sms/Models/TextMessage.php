<?php

namespace App\Sending\Sms\Models;

use App\Core\Authentication\Models\User;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use App\Core\RestApi\Traits\ApiObjectTrait;
use App\ActivityLog\Interfaces\EventObjectInterface;
use App\ActivityLog\Traits\EventObjectTrait;

/**
 * @property string      $id
 * @property string|null $contact_name
 * @property string      $to
 * @property string      $state
 * @property string      $message
 * @property User|null   $sent_by
 * @property string|null $twilio_id
 * @property int|null    $related_to_type
 * @property int|null    $related_to_id
 */
class TextMessage extends MultitenantModel implements EventObjectInterface
{
    use ApiObjectTrait;
    use AutoTimestamps;
    use EventObjectTrait;

    protected static function getProperties(): array
    {
        return [
            'id' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
            ),
            'contact_name' => new Property(
                type: Type::STRING,
                null: true,
            ),
            'to' => new Property(
                required: true,
            ),
            'state' => new Property(
                required: true,
            ),
            'message' => new Property(
                required: true,
            ),
            'sent_by' => new Property(
                null: true,
                belongs_to: User::class,
            ),
            'twilio_id' => new Property(
                null: true,
                in_array: false,
            ),
            'related_to_type' => new Property(
                type: Type::INTEGER,
                null: true,
                in_array: false,
            ),
            'related_to_id' => new Property(
                type: Type::INTEGER,
                null: true,
                in_array: false,
            ),
        ];
    }
}
