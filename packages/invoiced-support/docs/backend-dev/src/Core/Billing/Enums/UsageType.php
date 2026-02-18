<?php

namespace App\Core\Billing\Enums;

use RuntimeException;

enum UsageType: int
{
    case InvoicesPerMonth = 1;
    case CustomersPerMonth = 2;
    case Users = 3;
    case MoneyBilledPerMonth = 4;
    case Entities = 5;

    public function getName(): string
    {
        return match ($this) {
            self::InvoicesPerMonth => 'invoice',
            self::CustomersPerMonth => 'customer',
            self::Users => 'user',
            self::MoneyBilledPerMonth => 'money_billed',
            self::Entities => 'entities',
        };
    }

    public function getFriendlyName(): string
    {
        return match ($this) {
            self::InvoicesPerMonth => 'Invoices/Month',
            self::CustomersPerMonth => 'Customers/Month',
            self::Users => 'Users',
            self::MoneyBilledPerMonth => 'Money Billed/Month',
            self::Entities => 'Entities',
        };
    }

    /**
     * Gets the name of a single unit.
     */
    public function getUnit(): string
    {
        return match ($this) {
            self::InvoicesPerMonth => 'Invoice',
            self::CustomersPerMonth => 'Customer',
            self::Users => 'User',
            self::MoneyBilledPerMonth => '$1 USD Billed',
            self::Entities => 'Entity',
        };
    }

    public static function fromName(string $name): self
    {
        foreach (self::cases() as $case) {
            if ($case->getName() == $name) {
                return $case;
            }
        }

        throw new RuntimeException('Unknown usage type: '.$name);
    }
}
