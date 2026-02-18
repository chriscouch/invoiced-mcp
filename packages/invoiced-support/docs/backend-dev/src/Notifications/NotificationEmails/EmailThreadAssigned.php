<?php

namespace App\Notifications\NotificationEmails;

use App\Sending\Email\Models\EmailThread;

class EmailThreadAssigned extends AbstractNotificationEmail
{
    const THRESHOLD = 3;

    protected function getSubject(): string
    {
        return 'Conversation was assigned to you';
    }

    public function getVariables(array $events): array
    {
        if (count($events) > static::THRESHOLD) {
            return [
                'count' => count($events),
            ];
        }

        $ids = $this->getObjectIds($events);
        $items = EmailThread::where('id IN ('.implode(',', $ids).')')
            ->sort('id')->execute();

        $items = array_map(fn (EmailThread $item) => $item->toArray(), $items);

        return [
            'threads' => $items,
        ];
    }

    public function getTemplate(array $events): string
    {
        if (count($events) > static::THRESHOLD) {
            return 'notifications/email-thread-bulk';
        }

        return 'notifications/email-thread';
    }
}
