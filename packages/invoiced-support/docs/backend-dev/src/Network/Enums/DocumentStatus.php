<?php

namespace App\Network\Enums;

use RuntimeException;

enum DocumentStatus: int
{
    case PendingApproval = 1;
    case Approved = 2;
    case Rejected = 3;
    case Paid = 4;
    case Voided = 5;

    public static function fromName(string $name): self
    {
        foreach (self::cases() as $case) {
            if ($case->name === $name) {
                return $case;
            }
        }

        throw new RuntimeException('Case not found: '.$name);
    }
}
