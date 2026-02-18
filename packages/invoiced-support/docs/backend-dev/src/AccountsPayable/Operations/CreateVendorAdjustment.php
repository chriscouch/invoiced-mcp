<?php

namespace App\AccountsPayable\Operations;

use App\AccountsPayable\Ledger\AccountsPayableLedger;
use App\AccountsPayable\Models\Bill;
use App\AccountsPayable\Models\Vendor;
use App\AccountsPayable\Models\VendorAdjustment;
use App\AccountsPayable\Models\VendorCredit;
use App\Core\Ledger\Exception\LedgerException;
use App\Core\Orm\Exception\ModelException;

class CreateVendorAdjustment
{
    public function __construct(
        private AccountsPayableLedger $accountsPayableLedger,
    ) {
    }

    /**
     * Creates a new vendor adjustment.
     *
     * @throws ModelException
     */
    public function create(array $parameters): VendorAdjustment
    {
        // Create the adjustment
        $adjustment = new VendorAdjustment();

        if (isset($parameters['vendor']) && !$parameters['vendor'] instanceof Vendor) {
            $adjustment->vendor = Vendor::findOrFail($parameters['vendor']);
            unset($parameters['vendor']);
        }

        if (isset($parameters['bill']) && !$parameters['bill'] instanceof Bill) {
            $adjustment->bill = Bill::findOrFail($parameters['bill']);
            unset($parameters['bill']);
        }

        if (isset($parameters['vendor_credit']) && !$parameters['vendor_credit'] instanceof VendorCredit) {
            $adjustment->vendor_credit = VendorCredit::findOrFail($parameters['vendor_credit']);
            unset($parameters['vendor_credit']);
        }

        foreach ($parameters as $k => $v) {
            $adjustment->$k = $v;
        }

        $adjustment->saveOrFail();

        // Create the ledger entries
        $ledger = $this->accountsPayableLedger->getLedger($adjustment->tenant());
        try {
            $this->accountsPayableLedger->syncAdjustment($ledger, $adjustment);
        } catch (LedgerException $e) {
            throw new ModelException($e->getMessage(), $e->getCode(), $e);
        }

        return $adjustment;
    }
}
