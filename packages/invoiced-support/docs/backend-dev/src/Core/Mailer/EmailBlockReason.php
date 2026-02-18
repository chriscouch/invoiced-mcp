<?php

namespace App\Core\Mailer;

enum EmailBlockReason: int
{
    case PermanentBounce = 1;
    case Complaint = 2;

    public function toString(): string
    {
        return match ($this) {
            self::PermanentBounce => 'Permanent Bounce',
            self::Complaint => 'Spam Complaint',
        };
    }
}
