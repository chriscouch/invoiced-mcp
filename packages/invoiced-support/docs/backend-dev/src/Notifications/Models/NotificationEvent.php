<?php

namespace App\Notifications\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Notifications\Enums\NotificationEventType;
use Carbon\Carbon;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * Represents a notification that is stored in the DB.
 *
 * @property int    $id
 * @property int    $type
 * @property int    $object_id
 * @property string $message
 */
class NotificationEvent extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'type' => new Property(
                required: true,
            ),
            'object_id' => new Property(
                required: true,
            ),
            'message' => new Property(
                type: Type::STRING,
                mutable: Property::MUTABLE_CREATE_ONLY,
                null: true,
                default: null,
            ),
        ];
    }

    public function setType(NotificationEventType $type): void
    {
        $this->type = $type->toInteger();
    }

    public function getType(): NotificationEventType
    {
        return NotificationEventType::fromInteger($this->type);
    }

    public function toArray(): array
    {
        $result = parent::toArray();
        $result['type'] = $this->getType()->value;
        $result['created_at'] = Carbon::createFromTimestamp($this->created_at)->toIso8601String();
        $result['updated_at'] = Carbon::createFromTimestamp($this->updated_at)->toIso8601String();

        return $result;
    }
}
