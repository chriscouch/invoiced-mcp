<?php

namespace App\AccountsPayable\Enums;

enum VendorPaymentItemTypes: int
{
    case Application = 1;
    case ConvenienceFee = 2;

    public function toString(): string
    {
        return match ($this) {
            self::Application => 'application',
            self::ConvenienceFee => 'convenience_fee',
        };
    }
}
