<?php

namespace App\Sending\ValueObjects;

class ScheduledSendParameters
{
    public function __construct(private readonly ?array $to,
                                private readonly ?array $cc,
                                private readonly ?array $bcc,
                                private readonly ?string $subject,
                                private readonly ?string $message,
                                private readonly ?int $role)
    {
    }

    public function getTo(): ?array
    {
        return $this->to;
    }

    public function getRole(): ?int
    {
        return $this->role;
    }

    public function getCc(): ?array
    {
        return $this->cc;
    }

    public function getBcc(): ?array
    {
        return $this->bcc;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }
}
