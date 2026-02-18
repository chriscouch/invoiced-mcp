<?php

namespace App\AccountsPayable\Traits;

use App\AccountsPayable\Models\PayableDocument;
use App\AccountsPayable\Models\VendorCredit;
use App\Core\Ledger\Exception\LedgerException;
use App\Core\Orm\Exception\ModelException;

trait VendorCreditOperationTrait
{
    protected function getIdField(): string
    {
        return 'vendor_credit';
    }

    protected function makeNew(): VendorCredit
    {
        return new VendorCredit();
    }

    /**
     * @param VendorCredit $document
     */
    protected function ledgerSync(PayableDocument $document): void
    {
        try {
            $ledger = $this->accountsPayableLedger->getLedger($document->tenant());
            $this->accountsPayableLedger->syncVendorCredit($ledger, $document);
        } catch (LedgerException $e) {
            throw new ModelException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
