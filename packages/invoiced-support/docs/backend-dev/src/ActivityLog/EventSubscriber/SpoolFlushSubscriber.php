<?php

namespace App\ActivityLog\EventSubscriber;

use App\Core\Search\Libs\Search;
use App\ActivityLog\Libs\EventSpool;
use App\Integrations\AccountingSync\WriteSync\AccountingWriteSpool;
use App\Notifications\Libs\NotificationSpool;
use App\Sending\Email\Libs\EmailSpool;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;

/**
 * Flushes all in-memory spools at the end of a request.
 */
class SpoolFlushSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private AccountingWriteSpool $accountingWriteSpool,
        private EmailSpool $emailSpool,
        private EventSpool $eventSpool,
        private NotificationSpool $notificationSpool,
        private Search $search,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'kernel.terminate' => ['onKernelTerminate', -255],
        ];
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        // Email should be flushed before events because emails
        // can create events.
        $this->emailSpool->flush();
        $this->eventSpool->flush();
        $this->search->flushIndexSpools();
        $this->accountingWriteSpool->flush();
        $this->notificationSpool->flush();
    }
}
