<?php

namespace App\Core\Billing\Usage;

use App\Core\Billing\Enums\UsageType;
use App\Core\Billing\Interfaces\UsageInterface;

class UsageFactory
{
    public function __construct(
        private CustomersPerMonth $customersPerMonth,
        private InvoicesPerMonth $invoicesPerMonth,
        private IncludedUsers $includedUsers,
        private MoneyBilledPerMonth $billedPerMonth,
        private IncludedEntities $includedEntities,
    ) {
    }

    public function get(UsageType $type): UsageInterface
    {
        return match ($type) {
            UsageType::CustomersPerMonth => $this->customersPerMonth,
            UsageType::InvoicesPerMonth => $this->invoicesPerMonth,
            UsageType::Users => $this->includedUsers,
            UsageType::MoneyBilledPerMonth => $this->billedPerMonth,
            UsageType::Entities => $this->includedEntities,
        };
    }
}
