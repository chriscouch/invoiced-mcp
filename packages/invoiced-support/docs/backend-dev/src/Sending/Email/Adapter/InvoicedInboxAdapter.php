<?php

namespace App\Sending\Email\Adapter;

use App\Automations\Enums\AutomationEventType;
use App\Automations\ValueObjects\AutomationEvent;
use App\Core\Files\Exception\UploadException;
use App\Core\Files\Libs\AttachmentUploader;
use App\Core\Multitenant\TenantContext;
use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Libs\NotificationSpool;
use App\Sending\Email\Exceptions\AdapterEmailException;
use App\Sending\Email\Exceptions\SendEmailException;
use App\Sending\Email\Interfaces\AdapterInterface;
use App\Sending\Email\Interfaces\EmailBodyStorageInterface;
use App\Sending\Email\Interfaces\EmailInterface;
use App\Sending\Email\Libs\EmailSender;
use App\Sending\Email\Models\EmailParticipant;
use App\Sending\Email\Models\EmailThread;
use App\Sending\Email\Models\Inbox;
use App\Sending\Email\Models\InboxDecorator;
use App\Sending\Email\Models\InboxEmail;
use App\Sending\Email\ValueObjects\AbstractEmail;
use App\Sending\Email\ValueObjects\DocumentEmail;
use App\Sending\Email\ValueObjects\NamedAddress;
use Doctrine\DBAL\Connection;
use mikehaertl\tmp\File;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class InvoicedInboxAdapter implements AdapterInterface
{
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
        private readonly TenantContext $tenant,
        private readonly AttachmentUploader $attachmentUploader,
        private readonly EmailBodyStorageInterface $emailBodyStorage,
        private readonly Connection $database,
        private readonly NotificationSpool $notificationSpool,
        private readonly string $inboundEmailDomain,
    ) {
    }

    public function isInvoicedService(): bool
    {
        return false;
    }

    public function send(EmailInterface $message): void
    {
        $message->setAdapterUsed('invoiced_inbox');
        $toEmail = $message->getTo()[0]->getAddress();
        $inboxId = strstr($toEmail, '@', true);
        $toInbox = Inbox::queryWithoutMultitenancyUnsafe()
            ->where('external_id', $inboxId)
            ->oneOrNull();
        if (!$toInbox) {
            throw new AdapterEmailException('The mailbox you tried to reach does not exist: '.$toEmail);
        }

        // Change the from address to the inbox that this was sent message from
        if ($message instanceof AbstractEmail && $thread = $message->getEmailThread()) {
            $decorator = new InboxDecorator($thread->inbox, $this->inboundEmailDomain);
            $message->from($decorator->getNamedEmailAddress());
        }

        $this->tenant->runAs($toInbox->tenant(), function () use ($toInbox, $message) {
            $this->saveEmail($toInbox, $message);
        });
    }

    /**
     * Saves the email as an InboxEmail and associates it with a thread. Also creates the associated participant
     * and attachment objects.
     *
     * @throws SendEmailException
     */
    private function saveEmail(Inbox $inbox, EmailInterface $email): void
    {
        // save the email
        $inboxEmail = new InboxEmail();
        $inboxEmail->incoming = true;
        $inboxEmail->subject = $email->getSubject();
        $inboxEmail->message_id = (string) $email->getHeader('Message-ID');

        // associate email with thread
        if ($replyToEmail = $this->getReplyToEmail($email)) {
            $inboxEmail->reply_to_email = $replyToEmail;

            // reopen the thread if it was not open previously
            $thread = $replyToEmail->thread;
            if (EmailThread::STATUS_PENDING === $thread->status || EmailThread::STATUS_CLOSED === $thread->status) {
                $thread->status = EmailThread::STATUS_OPEN;
                $thread->save();
            }
            $inboxEmail->thread = $thread;
        } else {
            $inboxEmail->thread = $this->createThread($inbox, $email);
        }
        $inboxEmail->saveOrFail();

        // notify the recipient of the email
        $this->notificationSpool->spool(NotificationEventType::EmailReceived, $inboxEmail->tenant_id, $inboxEmail->id, $inboxEmail->thread->customer_id);
        $this->dispatcher->dispatch(new AutomationEvent($inboxEmail, AutomationEventType::ReceivedEmail->toInteger()), 'automation_event.dispatch');

        // save participant associations
        $this->saveEmailParticipants($email, (int) $inboxEmail->id());

        // save the body text in S3
        if ($emailText = $email->getPlainText()) {
            $this->emailBodyStorage->store($inboxEmail, $emailText, EmailBodyStorageInterface::TYPE_PLAIN_TEXT);
        }

        // save the html text in S3
        if ($html = $email->getHtml()) {
            $this->emailBodyStorage->store($inboxEmail, $html, EmailBodyStorageInterface::TYPE_HTML);
        }

        // save the header text in S3
        $headers = new Headers();
        EmailSender::copyHeadersIntoEmail($email, $headers);
        $this->emailBodyStorage->store($inboxEmail, $headers->toString(), EmailBodyStorageInterface::TYPE_HEADER);

        // process the files
        $fileObjects = [];
        foreach ($email->getAttachments() as $attachment) {
            try {
                // save it to a temporary file
                $tempFile = new File($attachment->getContent());
                $tempFile->delete = false; // handled by uploader
                $fileObjects[] = $this->attachmentUploader->upload($tempFile->getFileName(), $attachment->getFilename());
            } catch (UploadException $e) {
                throw new AdapterEmailException($e->getMessage(), $e->getCode(), $e);
            }
        }

        // associate the files
        foreach ($fileObjects as $fileObject) {
            $this->attachmentUploader->attachToObject($inboxEmail, $fileObject);
        }
    }

    /**
     * Searches the email we reply to.
     */
    private function getReplyToEmail(EmailInterface $email): ?InboxEmail
    {
        $references = (string) $email->getHeader('References');
        $messageIds = array_map('trim', explode(' ', $references));
        $replyTo = (string) $email->getHeader('In-Reply-To');
        $messageIds[] = trim($replyTo);
        $messageIds = array_filter($messageIds);

        if (0 == count($messageIds)) {
            return null;
        }

        $ids = array_map(fn ($reference) => $this->database->quote($reference), $messageIds);
        /** @var InboxEmail|null $email */
        $email = InboxEmail::where('message_id IN ('.implode(',', $ids).')')
            ->sort('date DESC')
            ->oneOrNull();

        return $email;
    }

    /**
     * Associates an InboxEmail with an EmailThread by creating a new one.
     */
    private function createThread(Inbox $inbox, EmailInterface $email): EmailThread
    {
        $thread = new EmailThread();
        $thread->name = $email->getSubject();
        $thread->inbox = $inbox;

        // Attempt to locate network connection
        // TODO

        // Attempt to locate customer or vendor based on network connection
        // TODO

        if ($email instanceof DocumentEmail) {
            // TODO: attempt to locate and set customer or vendor
            // TODO: set related to transaction
        }

        return $thread;
    }

    /**
     * Save the email participants and associates them with the email.
     */
    private function saveEmailParticipants(EmailInterface $email, int $emailId): void
    {
        $associations = [
            $this->saveEmailParticipant($emailId, EmailParticipant::FROM, $email->getFrom()),
        ];

        foreach ($email->getTo() as $address) {
            $associations[] = $this->saveEmailParticipant($emailId, EmailParticipant::TO, $address);
        }

        foreach ($email->getCc() as $address) {
            $associations[] = $this->saveEmailParticipant($emailId, EmailParticipant::CC, $address);
        }

        // save the participant associations
        foreach ($associations as $association) {
            $this->database->insert('EmailParticipantAssociations', $association);
        }
    }

    /**
     * Saves an email participant and creates an association structure.
     *
     * @param int    $emailId The email ID
     * @param string $type    The type of the participant
     *
     * @return array The association structure containing `email_id`, `participant_id`, and `type`
     */
    private function saveEmailParticipant(int $emailId, string $type, NamedAddress $participant): array
    {
        $emailParticipant = EmailParticipant::getOrCreate(
            $this->tenant->get(),
            $participant->getAddress(),
            (string) $participant->getName()
        );

        return [
            'email_id' => $emailId,
            'participant_id' => $emailParticipant->id(),
            'type' => $type,
        ];
    }
}
