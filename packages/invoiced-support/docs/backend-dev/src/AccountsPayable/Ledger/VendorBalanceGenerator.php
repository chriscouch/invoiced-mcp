<?php

namespace App\AccountsPayable\Ledger;

use App\AccountsPayable\Enums\ApAccounts;
use App\AccountsPayable\Models\Vendor;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Ledger\Ledger;
use App\Core\Ledger\ValueObjects\AccountingVendor;

class VendorBalanceGenerator
{
    /** @var Ledger[] */
    private array $ledgers = [];

    public function __construct(
        private AccountsPayableLedger $ledgerRepository,
    ) {
    }

    public function generate(Vendor $vendor): Money
    {
        if (!isset($this->ledgers[$vendor->tenant_id])) {
            $this->ledgers[$vendor->tenant_id] = $this->ledgerRepository->getLedger($vendor->tenant());
        }

        $accountingParty = new AccountingVendor($vendor->id);
        $amount = $this->ledgers[$vendor->tenant_id]->reporting->getAccountingPartyBalance($accountingParty, ApAccounts::AccountsPayable->value);

        return new Money($amount->getCurrency()->getCode(), (int) $amount->negative()->getAmount());
    }
}
