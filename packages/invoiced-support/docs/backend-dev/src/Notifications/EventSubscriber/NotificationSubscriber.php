<?php

namespace App\Notifications\EventSubscriber;

use App\EntryPoint\QueueJob\NotificationJob;
use App\ActivityLog\Models\Event;
use App\Notifications\Models\Notification;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Sends out any notifications for the event.
 */
class NotificationSubscriber implements EventSubscriberInterface
{
    public function __construct(private NotificationJob $notificationJob)
    {
    }

    public function onEventDispatch(Event $event): void
    {
        // send out any notifications
        $notifications = Notification::queryWithTenant($event->tenant())
            ->where('event', $event->type)
            ->where('enabled', true)
            ->all();

        foreach ($notifications as $notification) {
            if ($this->notificationJob->canSend($notification, $event)) {
                $this->notificationJob->queue($notification, $event);
            }
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'object_event.dispatch' => 'onEventDispatch',
        ];
    }
}
