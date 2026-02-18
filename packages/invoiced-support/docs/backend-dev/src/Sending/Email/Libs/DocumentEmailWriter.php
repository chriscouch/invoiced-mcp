<?php

namespace App\Sending\Email\Libs;

use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\Core\Files\Libs\AttachmentUploader;
use App\Core\Orm\Model;
use App\Sending\Email\Exceptions\SendEmailException;
use App\Sending\Email\Interfaces\EmailBodyStorageInterface;
use App\Sending\Email\Interfaces\EmailInterface;
use App\Sending\Email\Interfaces\OutgoingEmailWriterInterface;
use App\Sending\Email\Interfaces\SendableDocumentInterface;
use App\Sending\Email\Models\EmailParticipant;
use App\Sending\Email\Models\InboxEmail;
use App\Sending\Email\ValueObjects\DocumentEmail;
use Doctrine\DBAL\Connection;
use mikehaertl\tmp\File;

/**
 * Records a transaction email sent to a customer to our database.
 */
class DocumentEmailWriter extends AbstractEmailWriter implements OutgoingEmailWriterInterface
{
    public function __construct(
        EmailBodyStorageInterface $emailBodyStorage,
        private AttachmentUploader $attachmentUploader,
        Connection $database,
    ) {
        parent::__construct($emailBodyStorage, $database);
    }

    /**
     * @param DocumentEmail $email
     */
    public function write(EmailInterface $email): void
    {
        $emailModel = $this->buildInboxEmailModel($email);

        if ($emailModel instanceof InboxEmail) {
            self::markDocumentSent($email->getDocument(), $this->database);
            $email->sentEmail($emailModel);
        }
    }

    /**
     * Creates a new threaded email.
     */
    private function buildInboxEmailModel(DocumentEmail $message): ?InboxEmail
    {
        $thread = $message->getEmailThread();
        if (!$thread) {
            return null;
        }

        // create email
        $email = $message->toInboxEmail();
        $email->save();

        // create participants and associations
        $from = $message->getFrom();
        $this->createAssociation($message->getCompany(), $email->id, $from->getAddress(), $from->getName(), EmailParticipant::FROM);

        foreach ($message->getTo() as $to) {
            $this->createAssociation($message->getCompany(), $email->id, $to->getAddress(), $to->getName(), EmailParticipant::TO);
        }

        foreach ($message->getCc() as $cc) {
            $this->createAssociation($message->getCompany(), $email->id, $cc->getAddress(), $cc->getName(), EmailParticipant::CC);
        }

        foreach ($message->getBcc() as $bcc) {
            $this->createAssociation($message->getCompany(), $email->id, $bcc->getAddress(), $bcc->getName(), EmailParticipant::BCC);
        }

        // save message body
        try {
            $this->emailBodyStorage->store($email, (string) $message->getHtml(), EmailBodyStorageInterface::TYPE_HTML);
            $this->emailBodyStorage->store($email, (string) $message->getPlainText(), EmailBodyStorageInterface::TYPE_PLAIN_TEXT);
        } catch (SendEmailException $e) {
            $this->logger->error('Could not upload email message body', ['exception' => $e]);
        }

        // save file attachments
        foreach ($message->getAttachments() as $attachment) {
            // save to a temporary file
            $file = new File($attachment->getContent());
            $file->delete = false; // handled by uploader

            try {
                $fileObject = $this->attachmentUploader->upload($file->getFileName(), $attachment->getFilename(), $message->getCompany()->id);
                $this->attachmentUploader->attachToObject($email, $fileObject, $message->getCompany()->id);
            } catch (\Exception $e) {
                $this->logger->error('Could not upload sent email file attachment', ['exception' => $e]);

                continue;
            }
        }

        return $email;
    }

    /**
     * Marks the document as sent.
     */
    public static function markDocumentSent(SendableDocumentInterface $document, Connection $database): void
    {
        // cannot do anything here if the object being sent
        // is not a model or is not persisted to the DB
        if (!($document instanceof Model) || !$document->persisted()) {
            return;
        }

        // Set last_sent on invoices and estimates. The ORM is bypassed
        // in order to minimize concurrency errors with the "status" field.
        if ($document instanceof Invoice || $document instanceof Estimate) {
            $document->last_sent = time();
            $database->executeStatement('UPDATE '.$document->getTablename().' SET last_sent=? WHERE id=?', [$document->last_sent, $document->id]);
        }

        // Mark unsent receivable documents as sent only if
        // it is not marked sent or legacy invoice chasing is enabled.
        $company = $document->tenant();
        if ($document instanceof ReceivableDocument && (!$document->sent || ($company->features->has('legacy_chasing') && $company->accounts_receivable_settings->allow_chasing))) {
            $document->sent = true;
            $document->skipReconciliation();
            $document->skipClosedCheck()->save();
        }
    }
}
