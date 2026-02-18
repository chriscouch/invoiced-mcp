<?php

namespace App\Sending\Email\Libs;

use App\Core\Files\Models\Attachment;
use App\Core\Orm\Exception\ModelException;
use App\Sending\Email\Exceptions\SendEmailException;
use App\Sending\Email\Interfaces\EmailBodyStorageInterface;
use App\Sending\Email\Interfaces\EmailInterface;
use App\Sending\Email\Interfaces\OutgoingEmailWriterInterface;
use App\Sending\Email\Models\EmailParticipant;
use App\Sending\Email\ValueObjects\Email;

class CommonEmailWriter extends AbstractEmailWriter implements OutgoingEmailWriterInterface
{
    /**
     * @param Email $email
     *
     * @throws ModelException
     */
    public function write(EmailInterface $email): void
    {
        // create email
        $emailModel = $email->toInboxEmail();
        $emailModel->saveOrFail();

        // create participants and associations
        $from = $email->getFrom();
        $this->createAssociation($email->getCompany(), $emailModel->id, $from->getAddress(), $from->getName(), EmailParticipant::FROM);

        foreach ($email->getTo() as $value) {
            $name = $value->getName() ?? '';
            $this->createAssociation($email->getCompany(), $emailModel->id, $value->getAddress(), $name, EmailParticipant::TO);
        }
        foreach ($email->getCc() as $value) {
            $name = $value->getName() ?? '';
            $this->createAssociation($email->getCompany(), $emailModel->id, $value->getAddress(), $name, EmailParticipant::CC);
        }
        foreach ($email->getBcc() as $value) {
            $name = $value->getName() ?? '';
            $this->createAssociation($email->getCompany(), $emailModel->id, $value->getAddress(), $name, EmailParticipant::BCC);
        }

        // save message body as plain text
        try {
            $this->emailBodyStorage->store($emailModel, (string) $email->getPlainText(), EmailBodyStorageInterface::TYPE_PLAIN_TEXT);
        } catch (SendEmailException $e) {
            $this->logger->error('Could not upload email message body', ['exception' => $e]);
        }

        foreach ($email->getFiles() as $file) {
            $attachment = new Attachment();
            $attachment->tenant_id = (int) $email->getCompany()->id();
            $attachment->setParent($emailModel);
            $attachment->setFile($file);
            $attachment->saveOrFail();
        }

        $email->sentEmail($emailModel);
    }
}
