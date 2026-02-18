<?php

namespace App\Sending\Email\ValueObjects;

use App\Companies\Models\Company;
use App\Core\Authentication\Models\User;
use App\Core\Utils\InfuseUtility as Utility;
use App\Sending\Email\Interfaces\EmailInterface;
use App\Sending\Email\Models\EmailThread;
use App\Sending\Email\Models\InboxEmail;

/**
 * This class represents an email.
 */
abstract class AbstractEmail implements EmailInterface
{
    private string $id = '';
    private Company $company;
    private NamedAddress $from;
    /** @var NamedAddress[] */
    private array $to = [];
    /** @var NamedAddress[] */
    private array $cc = [];
    /** @var NamedAddress[] */
    private array $bcc = [];
    private string $subject = '';
    /** @var EmailAttachment[] */
    private array $attachments = [];
    private array $headers = [];
    /** @var callable|string|null */
    private $plainText;
    /** @var callable|string|null */
    private $html;
    /** @var callable|string|null */
    private $htmlWithTracking = '';
    private ?User $sentBy = null;
    private ?InboxEmail $sentEmail = null;
    private ?EmailThread $emailThread = null;
    private ?int $reply_to_email_id = null;
    private string $adapter = '';

    public function __construct(string $id = null)
    {
        $this->id = $id ?? strtolower(Utility::guid(false));
    }

    //
    // Setters
    //

    public function company(Company $company): static
    {
        $this->company = $company;

        return $this;
    }

    public function from(NamedAddress $from): static
    {
        $this->from = $from;

        return $this;
    }

    public function to(array $to): static
    {
        $this->to = $to;

        return $this;
    }

    public function cc(array $cc): static
    {
        $this->cc = $cc;

        return $this;
    }

    public function bcc(array $bcc): static
    {
        $this->bcc = $bcc;

        return $this;
    }

    public function subject(string $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * @param callable|string $plainText
     */
    public function plainText($plainText): static
    {
        $this->plainText = $plainText;

        return $this;
    }

    /**
     * @param callable|string $html
     */
    public function html($html, bool $withTracking = false): static
    {
        if ($withTracking) {
            $this->htmlWithTracking = $html;
        } else {
            $this->html = $html;
        }

        return $this;
    }

    /**
     * @param EmailAttachment[] $attachments
     */
    public function attachments(array $attachments): static
    {
        $this->attachments = $attachments;

        return $this;
    }

    public function headers(array $headers): static
    {
        $this->headers = $headers;

        return $this;
    }

    public function setMessageId(string $messageId): void
    {
        $this->headers['Message-ID'] = $messageId;
    }

    public function sentBy(User $user): static
    {
        $this->sentBy = $user;

        return $this;
    }

    /**
     * This should be called by the email transport after
     * delivery was attempted with the results.
     */
    public function sentEmail(InboxEmail $sentEmail): void
    {
        $this->sentEmail = $sentEmail;
    }

    public function emailThread(?EmailThread $emailThread): static
    {
        $this->emailThread = $emailThread;

        return $this;
    }

    public function setReplyToEmailId(?int $reply_to_email_id): static
    {
        $this->reply_to_email_id = $reply_to_email_id;

        return $this;
    }

    //
    // Getters
    //

    public function getId(): string
    {
        return $this->id;
    }

    public function getCompany(): Company
    {
        return $this->company;
    }

    public function getFrom(): NamedAddress
    {
        return $this->from;
    }

    public function getTo(): array
    {
        return $this->to;
    }

    public function getToEmails(): string
    {
        return implode(',', array_map(fn ($recipient) => $recipient->getAddress(), $this->to));
    }

    public function getCc(): array
    {
        return $this->cc;
    }

    public function getBcc(): array
    {
        return $this->bcc;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getAttachments(): array
    {
        return $this->attachments;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $key): ?string
    {
        return array_value($this->headers, $key);
    }

    public function getHtml(bool $withTracking = false): ?string
    {
        if ($withTracking && $this->htmlWithTracking) {
            if (is_callable($this->htmlWithTracking)) {
                $this->htmlWithTracking = call_user_func($this->htmlWithTracking);
            }

            return $this->htmlWithTracking;
        }

        if (is_callable($this->html)) {
            $this->html = call_user_func($this->html);
        }

        return $this->html;
    }

    public function getPlainText(): ?string
    {
        if (is_callable($this->plainText)) {
            $this->plainText = call_user_func($this->plainText);
        }

        return $this->plainText;
    }

    public function getSentBy(): ?User
    {
        return $this->sentBy;
    }

    public function getSentEmail(): ?InboxEmail
    {
        return $this->sentEmail;
    }

    public function getEmailThread(): ?EmailThread
    {
        return $this->emailThread;
    }

    public function getReplyToEmailId(): ?int
    {
        return $this->reply_to_email_id;
    }

    public function toInboxEmail(): InboxEmail
    {
        $email = new InboxEmail();
        $email->tenant_id = $this->company->id;
        if ($thread = $this->getEmailThread()) {
            $email->thread = $thread;
        }
        $email->incoming = false;
        $email->subject = $this->subject;
        $email->message_id = $this->getHeader('Message-ID');
        $email->sent_by = $this->sentBy;
        $email->reply_to_email_id = $this->reply_to_email_id;

        return $email;
    }

    public function getAdapterUsed(): string
    {
        return $this->adapter;
    }

    public function setAdapterUsed(string $adapter): void
    {
        $this->adapter = $adapter;
    }
}
