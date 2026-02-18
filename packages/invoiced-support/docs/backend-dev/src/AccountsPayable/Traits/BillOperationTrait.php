<?php

namespace App\AccountsPayable\Traits;

use App\AccountsPayable\Models\Bill;
use App\AccountsPayable\Models\PayableDocument;
use App\Core\Ledger\Exception\LedgerException;
use App\Core\Orm\Exception\ModelException;

trait BillOperationTrait
{
    protected function getIdField(): string
    {
        return 'bill';
    }

    protected function makeNew(): Bill
    {
        return new Bill();
    }

    /**
     * @param Bill $document
     */
    protected function ledgerSync(PayableDocument $document): void
    {
        try {
            $ledger = $this->accountsPayableLedger->getLedger($document->tenant());
            $this->accountsPayableLedger->syncBill($ledger, $document);
        } catch (LedgerException $e) {
            throw new ModelException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
