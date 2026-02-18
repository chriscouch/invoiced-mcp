<?php

namespace App\Sending\Email\Libs;

use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Libs\NotificationSpool;
use App\Sending\Email\Interfaces\EmailBodyStorageInterface;
use App\Sending\Email\Models\EmailParticipant;
use App\Sending\Email\Models\EmailThread;
use App\Sending\Email\Models\InboxEmail;
use Doctrine\DBAL\Connection;

class BounceEmailWriter extends AbstractEmailWriter
{
    public function __construct(EmailBodyStorageInterface $emailBodyStorage, Connection $database, private NotificationSpool $notificationSpool)
    {
        parent::__construct($emailBodyStorage, $database);
    }

    /**
     * Creates a bounce notification as a new incoming email.
     */
    public function write(string $bouncedAddress, string $reason, InboxEmail $originalEmail): void
    {
        // save the email
        $email = new InboxEmail();
        $email->incoming = true;
        $email->thread = $originalEmail->thread;
        $email->reply_to_email = $originalEmail;
        $email->subject = 'Delivery Status Notification (Failure)';
        $email->saveOrFail();

        // create a notification for the email
        $this->notificationSpool->spool(NotificationEventType::EmailReceived, $email->tenant_id, $email->id, $email->thread->customer_id);

        // save participant associations
        $this->createAssociation($email->tenant(), $email->id, $bouncedAddress, 'Mail Delivery Subsystem', EmailParticipant::FROM);
        $company = $originalEmail->tenant();
        $this->createAssociation($email->tenant(), $email->id, (string) $company->email, $company->getDisplayName(), EmailParticipant::TO);

        // save the body text in S3
        $reason = $reason ?: 'No specific reason was given by the receiving mail system.';
        $emailText = "Your message wasn't delivered to $bouncedAddress because a failure occurred:\n\n$reason";
        $this->emailBodyStorage->store($email, $emailText, EmailBodyStorageInterface::TYPE_PLAIN_TEXT);

        // reopen the email thread
        $thread = $originalEmail->thread;
        if (EmailThread::STATUS_OPEN != $thread->status) {
            $thread->status = EmailThread::STATUS_OPEN;
            $thread->saveOrFail();
        }
    }
}
