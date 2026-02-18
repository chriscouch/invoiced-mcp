<?php

namespace App\Notifications\Libs;

use App\Companies\Models\Member;
use App\Core\Mailer\Mailer;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Notifications\Interfaces\NotificationEmailInterface;
use App\Notifications\Models\NotificationEvent;
use Doctrine\DBAL\Connection;

class NotificationEmailSender implements StatsdAwareInterface
{
    use StatsdAwareTrait;

    public function __construct(
        protected Mailer $mailer,
        protected Connection $database
    ) {
    }

    /**
     * @param NotificationEvent[] $events
     */
    public function send(array $events, Member $member, NotificationEmailInterface $notificationEmail): void
    {
        if (!$events) {
            return;
        }

        $variables = $notificationEmail->getVariables($events);
        $variables['_moneyOptions'] = $member->tenant()->moneyFormat();
        $variables['tenant_id'] = $member->tenant()->id;

        $this->mailer->sendToUser(
            $member->user(),
            $notificationEmail->getMessage($events),
            $notificationEmail->getTemplate($events),
            $variables,
        );

        $this->markSent($events, $member);
    }

    /**
     * @param NotificationEvent[] $events
     */
    private function markSent(array $events, Member $member): void
    {
        $this->statsd->increment('notification.sent.email');

        // Mark as sent all recipients referenced by events in this batch.
        // First the recipient IDs are retrieved in order to prevent database deadlocks.
        $eventIds = array_map(fn (NotificationEvent $event) => $event->id, $events);
        if (0 == count($eventIds)) {
            return;
        }

        $ids = $this->database->fetchFirstColumn('SELECT id FROM NotificationRecipients WHERE member_id = :memberId AND notification_event_id IN ('.implode(',', $eventIds).')', [
            'memberId' => $member->id,
        ]);

        if (0 == count($ids)) {
            return;
        }

        $this->database->executeStatement('UPDATE NotificationRecipients SET sent=1 WHERE id IN ('.implode(',', $ids).')');
    }
}
