<?php

namespace App\Notifications\Emitters;

use App\Core\Authentication\Models\User;
use App\Core\Mailer\Mailer;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Models\Event;
use App\Notifications\Interfaces\EmitterInterface;

/**
 * @deprecated
 */
class EmailEmitter implements EmitterInterface
{
    public function __construct(
        private string $dashboardUrl,
        private Mailer $mailer,
    ) {
    }

    public function emit(Event $event, ?User $user = null): bool
    {
        if (!$user || $event->user_id == $user->id()) {
            return false;
        }

        if (EventType::ChargeFailed->value == $event->type) {
            $this->sendFailedCharge($event, $user);
        } else {
            $this->mailer->sendToUser($user, [
                'subject' => strip_tags($event->getMessage()->toString(false)),
            ], 'notification', $this->buildParams($event));
        }

        return true;
    }

    /**
     * Sends a failed charge email notification.
     */
    private function sendFailedCharge(Event $event, User $user): void
    {
        $object = $event->object;

        $this->mailer->sendToUser($user, [
            'subject' => strip_tags($event->getMessage()->toString(false)),
        ], 'notification-failed-charge', array_merge(
            $this->buildParams($event),
            [
                'failureReason' => $object->failure_message ?? null,
            ]));
    }

    private function buildParams(Event $event): array
    {
        $settingsUrl = $this->dashboardUrl.'/settings/notifications?tab=me&account='.$event->tenant_id;
        $message = $event->getMessage()->toString();

        return [
            'message' => $message,
            'href' => $event->href,
            'company' => $event->tenant(),
            'notificationsUrl' => $settingsUrl,
        ];
    }
}
