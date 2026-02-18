<?php

namespace App\PaymentProcessing\Enums;

use InvalidArgumentException;

enum PaymentMethodType: int
{
    case Ach = 1;
    case Affirm = 13;
    case BankTransfer = 2;
    case Card = 3;
    case Cash = 4;
    case Check = 5;
    case DirectDebit = 6;
    case Echeck = 7;
    case Klarna = 14;
    case Online = 9;
    case Other = 10;
    case PayPal = 11;
    case WireTransfer = 12;

    public function toString(): string
    {
        return match ($this) {
            PaymentMethodType::Ach => 'ach',
            PaymentMethodType::Affirm => 'affirm',
            PaymentMethodType::BankTransfer => 'bank_transfer',
            PaymentMethodType::Card => 'credit_card',
            PaymentMethodType::Cash => 'cash',
            PaymentMethodType::Check => 'check',
            PaymentMethodType::DirectDebit => 'direct_debit',
            PaymentMethodType::Echeck => 'echeck',
            PaymentMethodType::Klarna => 'klarna',
            PaymentMethodType::Online => 'online',
            PaymentMethodType::Other => 'other',
            PaymentMethodType::PayPal => 'paypal',
            PaymentMethodType::WireTransfer => 'wire_transfer',
        };
    }

    public static function fromString(string $input): self
    {
        return match ($input) {
            'ach' => PaymentMethodType::Ach,
            'affirm' => PaymentMethodType::Affirm,
            'bank_transfer' => PaymentMethodType::BankTransfer,
            'credit_card' => PaymentMethodType::Card,
            'cash' => PaymentMethodType::Cash,
            'check' => PaymentMethodType::Check,
            'direct_debit' => PaymentMethodType::DirectDebit,
            'echeck' => PaymentMethodType::Echeck,
            'klarna' => PaymentMethodType::Klarna,
            'online' => PaymentMethodType::Online,
            'other' => PaymentMethodType::Other,
            'paypal' => PaymentMethodType::PayPal,
            'wire_transfer' => PaymentMethodType::WireTransfer,
            default => throw new InvalidArgumentException('Unrecognized payment method: '.$input),
        };
    }
}
