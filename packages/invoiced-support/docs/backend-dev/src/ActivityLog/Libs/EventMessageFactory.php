<?php

namespace App\ActivityLog\Libs;

use App\Core\Utils\Enums\ObjectType;
use App\ActivityLog\Libs\Messages\BaseMessage;
use App\ActivityLog\Libs\Messages\DefaultMessage;
use App\ActivityLog\Libs\Messages\PaymentSourceMessage;
use App\ActivityLog\Models\Event;
use ICanBoogie\Inflector;
use RuntimeException;

class EventMessageFactory
{
    public static function make(Event $event): BaseMessage
    {
        // loads the event message class based on the
        // object name, i.e. invoice -> InvoiceMessage
        $class = 'App\ActivityLog\Libs\Messages\\';
        try {
            // object_type property is a fallback for legacy records without object_type_id
            $objectType = ObjectType::tryFrom((int) $event->object_type_id) ?? ObjectType::fromTypeName($event->object_type);
            $inflector = Inflector::get();
            $class .= $inflector->camelize($objectType->typeName());
            $class .= 'Message';

            if (!class_exists($class)) {
                $class = DefaultMessage::class;
            }
        } catch (RuntimeException) {
            if ('payment_source' == $event->object_type) {
                $class = PaymentSourceMessage::class;
            } else {
                $class = DefaultMessage::class;
            }
        }

        $company = $event->tenant();
        $object = (array) json_decode((string) json_encode($event->object), true); // deep array conversion
        $associations = $event->getAssociations();
        $previous = (array) $event->previous;

        return new $class($company, $event->type, $object, $associations, $previous); /* @phpstan-ignore-line */
    }
}
