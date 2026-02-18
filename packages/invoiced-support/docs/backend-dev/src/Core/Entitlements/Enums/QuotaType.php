<?php

namespace App\Core\Entitlements\Enums;

use InvalidArgumentException;

enum QuotaType: int
{
    case Users = 1;
    case TransactionsPerDay = 2;
    case NewCompanyLimit = 3;
    case MaxOpenNetworkInvitations = 4;
    case MaxDocumentVersions = 5;
    case VendorPayDailyLimit = 6;
    case CustomerEmailDailyLimit = 7;

    public function defaultLimit(): ?int
    {
        return match ($this) {
            self::MaxDocumentVersions => 10,
            self::MaxOpenNetworkInvitations => 5,
            self::NewCompanyLimit => 3,
            self::TransactionsPerDay => null,
            self::Users => null,
            self::VendorPayDailyLimit => 100000,
            self::CustomerEmailDailyLimit => null,
        };
    }

    public function getName(): string
    {
        return match ($this) {
            self::Users => 'users',
            self::TransactionsPerDay => 'transactions_per_day',
            self::NewCompanyLimit => 'new_company_limit',
            self::VendorPayDailyLimit => 'vendor_pay_daily_limit',
            self::CustomerEmailDailyLimit => 'aws_email_daily_limit',
            default => throw new InvalidArgumentException('Does not have name: '.$this->name),
        };
    }

    public static function fromString(string $id): self
    {
        return match ($id) {
            'users' => self::Users,
            'transactions_per_day' => self::TransactionsPerDay,
            'new_company_limit' => self::NewCompanyLimit,
            'vendor_pay_daily_limit' => self::VendorPayDailyLimit,
            'aws_email_daily_limit' => self::CustomerEmailDailyLimit,
            default => throw new InvalidArgumentException('Unrecognized quota: '.$id),
        };
    }
}
