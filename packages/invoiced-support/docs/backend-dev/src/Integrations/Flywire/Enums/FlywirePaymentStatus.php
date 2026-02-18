<?php

namespace App\Integrations\Flywire\Enums;

use InvalidArgumentException;

enum FlywirePaymentStatus: int
{
    case Initiated = 1;
    case Processed = 2;
    case Guaranteed = 3;
    case Delivered = 4;
    case Failed = 5;
    case Canceled = 6;
    case Reversed = 7;

    public static function fromString(string $status): self
    {
        return match ($status) {
            'processing', 'initiated' => self::Initiated,
            'verification', 'processed' => self::Processed,
            'on_hold', 'guaranteed' => self::Guaranteed,
            'delivered' => self::Delivered,
            'failed' => self::Failed,
            'cancelled', 'canceled' => self::Canceled,
            'reversed' => self::Reversed,
            default => throw new InvalidArgumentException("Invalid status: $status"),
        };
    }

    public function toString(): string
    {
        return match ($this) {
            self::Initiated => 'initiated',
            self::Processed => 'processed',
            self::Guaranteed => 'guaranteed',
            self::Delivered => 'delivered',
            self::Failed => 'failed',
            self::Canceled => 'canceled',
            self::Reversed => 'reversed',
        };
    }
}
