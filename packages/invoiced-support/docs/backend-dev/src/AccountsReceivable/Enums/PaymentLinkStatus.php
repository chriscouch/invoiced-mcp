<?php

namespace App\AccountsReceivable\Enums;

use InvalidArgumentException;

enum PaymentLinkStatus: int
{
    case Active = 1;
    case Completed = 2;
    case Deleted = 3;

    public function toString(): string
    {
        return match ($this) {
            self::Active => 'active',
            self::Completed => 'completed',
            self::Deleted => 'deleted',
        };
    }

    public static function fromString(string $status): self
    {
        return match ($status) {
            'active' => self::Active,
            'completed' => self::Completed,
            'deleted' => self::Deleted,
            default => throw new InvalidArgumentException("Invalid status: $status"),
        };
    }
}
