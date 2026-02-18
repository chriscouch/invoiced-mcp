<?php

namespace App\Sending\Email\Libs;

use App\AccountsReceivable\Models\ReceivableDocument;
use App\Core\Files\Models\Attachment;
use App\Core\Files\Models\File;
use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Libs\NotificationSpool;
use App\Sending\Email\Exceptions\SendEmailException;
use App\Sending\Email\Interfaces\EmailBodyStorageInterface;
use App\Sending\Email\Models\EmailParticipant;
use App\Sending\Email\Models\EmailThread;
use App\Sending\Email\Models\InboxEmail;
use Doctrine\DBAL\Connection;

class CommentEmailWriter extends AbstractEmailWriter
{
    public function __construct(
        EmailBodyStorageInterface $emailBodyStorage,
        Connection $database,
        private NotificationSpool $notificationSpool,
    ) {
        parent::__construct($emailBodyStorage, $database);
    }

    /**
     * @param File[] $files
     */
    public function write(ReceivableDocument $document, bool $incoming, ?string $customerEmail, string $text, array $files): ?InboxEmail
    {
        $company = $document->tenant();
        $inbox = $company->accounts_receivable_settings->inbox;
        if (!$inbox) {
            return null;
        }

        if (!$customerEmail) {
            $contacts = $document->customer()->emailContacts();
            $customerEmail = count($contacts) > 0 ? $contacts[0]['email'] : 'no-reply@invoiced.com';
        }

        $customer = $document->customer();

        // find or create thread
        $thread = EmailThread::where('inbox_id', $inbox->id())
            ->where('related_to_type', $document->getSendObjectType()?->value)
            ->where('related_to_id', $document)
            ->oneOrNull();

        $replyToEmail = null;
        if ($thread) {
            // get the latest email in the thread and reply to it
            $replyToEmail = InboxEmail::where('thread_id', $thread)
                ->sort('id desc')
                ->oneOrNull();
            if ($incoming && EmailThread::STATUS_CLOSED == $thread->status) {
                $thread->status = EmailThread::STATUS_OPEN;
                $thread->save();
            }
        } else {
            $thread = new EmailThread();
            $thread->inbox = $inbox;
            $thread->customer = $customer;
            $thread->related_to_type = $document->getSendObjectType();
            $thread->related_to_id = $document->getSendId();
            $thread->name = $document->number;
            $thread->status = $incoming ? EmailThread::STATUS_OPEN : EmailThread::STATUS_CLOSED;
            $thread->save();
        }

        // create email
        $email = new InboxEmail();
        $email->thread = $thread;
        $email->incoming = $incoming;
        $email->subject = $document->number;
        $email->reply_to_email = $replyToEmail;
        $email->save();

        // create participants and associations
        if ($incoming) {
            $this->createAssociation($company, $email->id, (string) $customerEmail, $customer->name, EmailParticipant::FROM);
            $this->createAssociation($company, $email->id, (string) $company->email, $company->getDisplayName(), EmailParticipant::TO);
        } else {
            $this->createAssociation($company, $email->id, (string) $customerEmail, $customer->name, EmailParticipant::TO);
            $this->createAssociation($company, $email->id, (string) $company->email, $company->getDisplayName(), EmailParticipant::FROM);
        }

        // save message body as plain text
        try {
            $this->emailBodyStorage->store($email, $text, EmailBodyStorageInterface::TYPE_PLAIN_TEXT);
        } catch (SendEmailException $e) {
            $this->logger->error('Could not upload email message body', ['exception' => $e]);
        }

        // save file attachments
        foreach ($files as $fileObject) {
            $attachment = new Attachment();
            $attachment->setParent($email);
            $attachment->setFile($fileObject);
            $attachment->save();
        }

        // create a user notification
        if ($incoming) {
            $this->notificationSpool->spool(NotificationEventType::EmailReceived, $email->tenant_id, $email->id, $customer->id);
        }

        return $email;
    }
}
