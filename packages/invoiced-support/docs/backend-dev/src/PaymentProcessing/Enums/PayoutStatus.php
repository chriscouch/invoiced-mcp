<?php

namespace App\PaymentProcessing\Enums;

use InvalidArgumentException;

enum PayoutStatus: int
{
    case Completed = 1;
    case Pending = 2;
    case Canceled = 3;
    case Failed = 4;

    public function toString(): string
    {
        return match ($this) {
            self::Canceled => 'canceled',
            self::Completed => 'completed',
            self::Failed => 'failed',
            self::Pending => 'pending',
        };
    }

    public static function fromString(string $status): self
    {
        return match ($status) {
            'canceled' => self::Canceled,
            'completed' => self::Completed,
            'failed' => self::Failed,
            'pending' => self::Pending,
            default => throw new InvalidArgumentException("Invalid status: $status"),
        };
    }
}
