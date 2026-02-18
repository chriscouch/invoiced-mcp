<?php

namespace App\Enums;

enum ContractRenewalStatus: string
{
    case AutoRenew = 'auto';
    case CanceledRenewal = 'cancel';
    case ManualRenewal = 'manual';
    case Renewed = 'renewed';

    public function getName(): string
    {
        return match ($this) {
            self::AutoRenew => 'Auto-Renewal',
            self::CanceledRenewal => 'Canceled Renewal',
            self::ManualRenewal => 'Manual Renewal',
            self::Renewed => 'Renewed',
        };
    }
}
