<?php

namespace App\Core\EventSubscriber;

use App\Companies\Libs\NumberingSequence;
use App\Core\Database\BeginTransactionEvent;
use App\Core\Database\RollBackTransactionEvent;
use App\Core\Search\Libs\Search;
use App\ActivityLog\Libs\EventSpool;
use App\Integrations\AccountingSync\WriteSync\AccountingWriteSpool;
use App\Notifications\Libs\NotificationSpool;
use App\Sending\Email\Libs\EmailSpool;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class DatabaseTransactionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private AccountingWriteSpool $accountingWriteSpool,
        private EmailSpool $emailSpool,
        private EventSpool $eventSpool,
        private NotificationSpool $notificationSpool,
        private Search $search,
    ) {
    }

    public function beginTransaction(): void
    {
        // Flush any spools or pending operations
        // here so that if we roll back they are not lost.
        // Email should be flushed before events because emails
        // can create events.
        $this->emailSpool->flush();
        $this->eventSpool->flush();
        $this->search->flushIndexSpools();
        $this->accountingWriteSpool->flush();
        $this->notificationSpool->flush();
    }

    public function rollback(): void
    {
        // clear any spooled operations
        $this->emailSpool->clear();
        $this->eventSpool->clear();
        $this->search->clearIndexSpools();
        $this->accountingWriteSpool->clear();
        $this->notificationSpool->clear();

        // reset the numbering sequences
        NumberingSequence::resetCache();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeginTransactionEvent::class => 'beginTransaction',
            RollBackTransactionEvent::class => 'rollback',
        ];
    }
}
