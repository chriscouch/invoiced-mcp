<?php

namespace App\Sending\Email\ValueObjects;

use App\Sending\Email\Interfaces\SendableDocumentInterface;
use App\Sending\Email\Models\EmailTemplate;
use App\Sending\Email\Models\InboxEmail;

/**
 * This class represents an email that is being sent to
 * an end customer, on behalf of a business. A document,
 * like an invoice or statement, is the subject of the email.
 */
class DocumentEmail extends AbstractEmail
{
    private SendableDocumentInterface $document;
    private EmailTemplate $emailTemplate;
    private string $body = '';
    private array $trackingPixels = [];

    //
    // Setters
    //

    public function document(SendableDocumentInterface $document): DocumentEmail
    {
        $this->document = $document;

        return $this;
    }

    public function body(string $body): static
    {
        $this->body = $body;

        return $this;
    }

    public function emailTemplate(EmailTemplate $emailTemplate): static
    {
        $this->emailTemplate = $emailTemplate;

        return $this;
    }

    //
    // Getters
    //

    public function getEmailTemplate(): EmailTemplate
    {
        return $this->emailTemplate;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getDocument(): SendableDocumentInterface
    {
        return $this->document;
    }

    public function getTrackingPixel(string $email): TrackingPixel
    {
        if (!isset($this->trackingPixels[$email])) {
            $pixel = new TrackingPixel();
            $this->trackingPixels[$email] = $pixel;
        }

        return $this->trackingPixels[$email];
    }

    public function toInboxEmail(): InboxEmail
    {
        $email = parent::toInboxEmail();
        $email->tracking_id = $this->getTrackingPixel($this->getToEmails())->getId();

        return $email;
    }
}
