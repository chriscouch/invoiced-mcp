<?php

namespace App\Integrations\Flywire\Enums;

use InvalidArgumentException;

enum FlywireRefundStatus: int
{
    case Initiated = 1;
    case Received = 2;
    case Finished = 3;
    case Returned = 4;
    case Canceled = 5;
    case Pending = 6;

    public static function fromString(string $status): self
    {
        return match ($status) {
            'initiated' => self::Initiated,
            'received' => self::Received,
            'pending' => self::Pending,
            'finished' => self::Finished,
            'returned' => self::Returned,
            'cancelled' => self::Canceled,
            default => throw new InvalidArgumentException("Invalid status: $status"),
        };
    }

    public function toString(): string
    {
        return match ($this) {
            self::Initiated => 'initiated',
            self::Received => 'received',
            self::Pending => 'pending',
            self::Finished => 'finished',
            self::Returned => 'returned',
            self::Canceled => 'canceled',
        };
    }
}
