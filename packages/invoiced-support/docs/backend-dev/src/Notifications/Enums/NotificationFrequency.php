<?php

namespace App\Notifications\Enums;

use InvalidArgumentException;

enum NotificationFrequency: string
{
    case Never = 'never';
    case Instant = 'instantly';
    case Daily = 'daily';
    case Weekly = 'weekly';

    public function toInteger(): int
    {
        return match ($this) {
            self::Never => 0,
            self::Instant => 1,
            self::Daily => 2,
            self::Weekly => 3,
        };
    }

    public static function fromInteger(int $id): self
    {
        foreach (self::cases() as $case) {
            if ($case->toInteger() == $id) {
                return $case;
            }
        }

        throw new InvalidArgumentException('Integer not mapped: '.$id);
    }
}
