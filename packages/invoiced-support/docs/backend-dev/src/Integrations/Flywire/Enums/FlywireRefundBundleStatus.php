<?php

namespace App\Integrations\Flywire\Enums;

use InvalidArgumentException;

enum FlywireRefundBundleStatus: int
{
    case Pending = 1;
    case Approved = 2;
    case Debited = 3;
    case Received = 4;

    public static function fromString(string $status): self
    {
        return match ($status) {
            'pending' => self::Pending,
            'approved' => self::Approved,
            'debited' => self::Debited,
            'received' => self::Received,
            default => throw new InvalidArgumentException("Invalid status: $status"),
        };
    }
}
