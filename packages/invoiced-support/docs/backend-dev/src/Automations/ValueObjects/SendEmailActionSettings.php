<?php

namespace App\Automations\ValueObjects;

class SendEmailActionSettings extends AbstractActionSettings
{
    public function __construct(
        public array $to,
        public string $subject,
        public string $body,
        public array $cc,
        public array $bcc,
    ) {
    }

    public static function fromSettings(object $settings): self
    {
        return new self(
            $settings->to,
            $settings->subject,
            $settings->body,
            $settings->cc ?? [],
            $settings->bcc ?? [],
        );
    }
}
