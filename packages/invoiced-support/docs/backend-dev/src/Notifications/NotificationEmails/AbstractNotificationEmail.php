<?php

namespace App\Notifications\NotificationEmails;

use App\Notifications\Interfaces\NotificationEmailInterface;
use App\Notifications\Models\NotificationEvent;
use Doctrine\DBAL\Connection;

abstract class AbstractNotificationEmail implements NotificationEmailInterface
{
    public function __construct(
        protected Connection $database
    ) {
    }

    abstract protected function getSubject(): string;

    public function getMessage(array $events): array
    {
        return [
            'subject' => $this->getSubject(),
        ];
    }

    /**
     * @param NotificationEvent[] $events
     *
     * @return int[]
     */
    protected function getObjectIds(array $events): array
    {
        return array_map(fn (NotificationEvent $event) => $event->object_id, $events);
    }
}
