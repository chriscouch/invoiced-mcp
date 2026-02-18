<?php

namespace App\CashApplication\Enums;

use InvalidArgumentException;

enum PaymentItemIntType: int
{
    case AppliedCredit = 1;
    case ConvenienceFee = 2;
    case Credit = 3;
    case CreditNote = 4;
    case DocumentAdjustment = 5;
    case Estimate = 6;
    case Invoice = 7;

    public static function fromString(string $type): self
    {
        return match ($type) {
            'applied_credit' => self::AppliedCredit,
            'convenience_fee' => self::ConvenienceFee,
            'credit' => self::Credit,
            'credit_note' => self::CreditNote,
            'document_adjustment' => self::DocumentAdjustment,
            'estimate' => self::Estimate,
            'invoice' => self::Invoice,
            default => throw new InvalidArgumentException('Unrecognized type: '.$type),
        };
    }

    public function toString(): string
    {
        return match ($this) {
            self::AppliedCredit => 'applied_credit',
            self::ConvenienceFee => 'convenience_fee',
            self::Credit => 'credit',
            self::CreditNote => 'credit_note',
            self::DocumentAdjustment => 'document_adjustment',
            self::Estimate => 'estimate',
            self::Invoice => 'invoice',
        };
    }
}
