<?php

namespace App\Core\Billing\Enums;

enum BillingInterval: int
{
    case Monthly = 1;
    case Yearly = 2;
    case Quarterly = 3;
    case Semiannually = 4;

    public function getIdName(): string
    {
        return strtolower($this->name);
    }

    public function numMonths(): int
    {
        return match ($this) {
            BillingInterval::Monthly => 1,
            BillingInterval::Yearly => 12,
            BillingInterval::Quarterly => 3,
            BillingInterval::Semiannually => 6,
        };
    }

    public function getUnitName(): string
    {
        return match ($this) {
            BillingInterval::Monthly => 'Month',
            BillingInterval::Yearly => 'Year',
            BillingInterval::Quarterly => 'Quarter',
            BillingInterval::Semiannually => '6 Months',
        };
    }
}
