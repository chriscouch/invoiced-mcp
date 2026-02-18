<?php

namespace App\Notifications\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Enums\NotificationFrequency;
use App\Core\Orm\Property;

/**
 * @property int $notification_type
 * @property int $frequency
 * @property int $id
 */
abstract class AbstractNotificationEventSetting extends MultitenantModel
{
    protected static function autoDefinitionNotificationSetting(): array
    {
        return [
            // NotificationEventType
            'notification_type' => new Property(
                required: true,
            ),
            // NotificationFrequency
            'frequency' => new Property(
                required: true,
                validate: ['range', 'min' => 0, 'max' => 3],
            ),
        ];
    }

    public function setNotificationType(NotificationEventType $type): void
    {
        $this->notification_type = $type->toInteger();
    }

    public function getNotificationType(): NotificationEventType
    {
        return NotificationEventType::fromInteger($this->notification_type);
    }

    public function setFrequency(NotificationFrequency $frequency): void
    {
        $this->frequency = $frequency->toInteger();
    }

    public function getFrequency(): NotificationFrequency
    {
        return NotificationFrequency::fromInteger($this->frequency);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'notification_type' => $this->getNotificationType()->value,
            'frequency' => $this->getFrequency()->value,
        ];
    }
}
