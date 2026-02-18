<?php

namespace App\Sending\Email\Interfaces;

use App\Companies\Models\Company;
use App\Core\Authentication\Models\User;
use App\Sending\Email\Models\EmailThread;
use App\Sending\Email\Models\InboxEmail;
use App\Sending\Email\ValueObjects\EmailAttachment;
use App\Sending\Email\ValueObjects\NamedAddress;

interface EmailInterface
{
    /**
     * Gets the randomly generated ID for this email.
     * This is not the same as Message-ID header, although
     * it might be used in the Message-ID.
     */
    public function getId(): string;

    public function getCompany(): Company;

    public function getFrom(): NamedAddress;

    /**
     * @return NamedAddress[]
     */
    public function getTo(): array;

    /**
     * Gets a comma-separated list of the email addresses
     * that are recipients.
     */
    public function getToEmails(): string;

    /**
     * @return NamedAddress[]
     */
    public function getCc(): array;

    /**
     * @return NamedAddress[]
     */
    public function getBcc(): array;

    public function getSubject(): string;

    /**
     * @return EmailAttachment[]
     */
    public function getAttachments(): array;

    public function getHeaders(): array;

    /**
     * Gets an email header.
     */
    public function getHeader(string $key): ?string;

    /**
     * Gets the HTML message body.
     */
    public function getHtml(bool $withTracking = false): ?string;

    /**
     * Gets the plain text message body.
     */
    public function getPlainText(): ?string;

    public function getSentBy(): ?User;

    public function getSentEmail(): ?InboxEmail;

    public function getEmailThread(): ?EmailThread;

    public function getReplyToEmailId(): ?int;

    public function toInboxEmail(): InboxEmail;

    public function setMessageId(string $messageId): void;

    public function setAdapterUsed(string $adapter): void;

    public function getAdapterUsed(): string;

    /**
     * @param NamedAddress[] $to
     */
    public function to(array $to): static;

    /**
     * @param NamedAddress[] $cc
     */
    public function cc(array $cc): static;

    /**
     * @param NamedAddress[] $bcc
     */
    public function bcc(array $bcc): static;
}
