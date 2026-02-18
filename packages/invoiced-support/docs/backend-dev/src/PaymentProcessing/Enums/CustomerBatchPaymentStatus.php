<?php

namespace App\PaymentProcessing\Enums;

enum CustomerBatchPaymentStatus: int
{
    case Created = 1;
    case Processing = 2;
    case Queued = 3;
    case Finished = 4;
    case Voided = 5;

    public static function fromName(string $name): self
    {
        foreach (self::cases() as $status) {
            if ($name === $status->name) {
                return $status;
            }
        }
        throw new \ValueError("$name is not a valid backing value for enum ".self::class);
    }
}
