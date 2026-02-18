<?php

namespace App\AccountsPayable\Operations;

use App\AccountsPayable\Ledger\AccountsPayableLedger;
use App\AccountsPayable\Models\Bill;
use App\AccountsPayable\Models\Vendor;
use App\AccountsPayable\Models\VendorAdjustment;
use App\Core\Ledger\Exception\LedgerException;
use App\Core\Orm\Exception\ModelException;

class EditVendorAdjustment
{
    public function __construct(
        private AccountsPayableLedger $accountsPayableLedger,
    ) {
    }

    /**
     * Edits a vendor adjustment.
     *
     * @throws ModelException
     */
    public function edit(VendorAdjustment $adjustment, array $parameters): void
    {
        if (isset($parameters['vendor']) && !$parameters['vendor'] instanceof Vendor) {
            $adjustment->vendor = Vendor::findOrFail($parameters['vendor']);
            unset($parameters['vendor']);
        }

        if (isset($parameters['bill']) && !$parameters['bill'] instanceof Bill) {
            $adjustment->bill = Bill::findOrFail($parameters['bill']);
            unset($parameters['bill']);
        }

        foreach ($parameters as $k => $v) {
            $adjustment->$k = $v;
        }

        $adjustment->saveOrFail();

        // make the ledger entries
        $ledger = $this->accountsPayableLedger->getLedger($adjustment->tenant());
        try {
            $this->accountsPayableLedger->syncAdjustment($ledger, $adjustment);
        } catch (LedgerException $e) {
            throw new ModelException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
