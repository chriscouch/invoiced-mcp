<?php

namespace App\Core\Ledger\ValueObjects;

use App\Core\Ledger\Enums\PartyType;

final class AccountingVendor extends AccountingParty
{
    public function __construct(int $id)
    {
        parent::__construct(PartyType::Vendor, $id);
    }
}
