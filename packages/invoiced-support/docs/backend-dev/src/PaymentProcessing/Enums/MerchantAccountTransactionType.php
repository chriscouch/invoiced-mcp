<?php

namespace App\PaymentProcessing\Enums;

use InvalidArgumentException;

enum MerchantAccountTransactionType: int
{
    case Payment = 1;
    case Payout = 2;
    case PayoutReversal = 3;
    case Fee = 4;
    case Refund = 5;
    case Dispute = 6;
    case DisputeReversal = 7;
    case Adjustment = 8;
    case TopUp = 9;
    case RefundReversal = 10;

    public function toString(): string
    {
        return match ($this) {
            self::Payment => 'payment',
            self::Payout => 'payout',
            self::PayoutReversal => 'payout_reversal',
            self::Fee => 'fee',
            self::Refund => 'refund',
            self::Dispute => 'dispute',
            self::DisputeReversal => 'dispute_reversal',
            self::Adjustment => 'adjustment',
            self::TopUp => 'topup',
            self::RefundReversal => 'refund_reversal',
        };
    }

    public static function fromString(string $status): self
    {
        return match ($status) {
            'payment' => self::Payment,
            'payout' => self::Payout,
            'payout_reversal' => self::PayoutReversal,
            'fee' => self::Fee,
            'refund' => self::Refund,
            'dispute' => self::Dispute,
            'dispute_reversal' => self::DisputeReversal,
            'adjustment' => self::Adjustment,
            'topup' => self::TopUp,
            'refund_reversal' => self::RefundReversal,
            default => throw new InvalidArgumentException("Invalid status: $status"),
        };
    }
}
