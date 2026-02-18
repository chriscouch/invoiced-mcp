<?php

namespace App\AccountsPayable\Operations;

use App\AccountsPayable\Ledger\AccountsPayableLedger;
use App\AccountsPayable\Models\VendorPayment;
use App\AccountsPayable\Models\VendorPaymentItem;
use App\Core\Ledger\Exception\LedgerException;
use App\Core\Orm\Exception\ModelException;

class EditVendorPayment extends AbstractSaveVendorPayment
{
    public function __construct(
        private AccountsPayableLedger $accountsPayableLedger,
        BillStatusTransition $billStatusTransition,
    ) {
        parent::__construct($billStatusTransition);
    }

    /**
     * Edits a vendor payment.
     *
     * @throws ModelException
     */
    public function edit(VendorPayment $payment, array $parameters, ?array $appliedTo = null): void
    {
        foreach ($parameters as $k => $v) {
            $payment->$k = $v;
        }

        $payment->saveOrFail();

        // Edit payment items
        if (null !== $appliedTo) {
            $items = $this->saveItems($payment, $appliedTo);

            // Delete unsaved payment items
            $ids = [];
            foreach ($items as $lineItem) {
                $ids[] = $lineItem->id;
            }
            $this->removeDeletedItems($payment, $ids);
        }

        $this->validateAppliedAmount($payment);

        // make the ledger entries
        $ledger = $this->accountsPayableLedger->getLedger($payment->tenant());
        try {
            $this->accountsPayableLedger->syncPayment($ledger, $payment);
            $this->saveRelatedDocumentStatuses($payment);
        } catch (LedgerException $e) {
            throw new ModelException($e->getMessage(), $e->getCode(), $e);
        }
    }

    private function removeDeletedItems(VendorPayment $payment, array $ids): void
    {
        $query = VendorPaymentItem::getDriver()
            ->getConnection(null)
            ->createQueryBuilder()
            ->delete((new VendorPaymentItem())->getTablename())
            ->andWhere('tenant_id = '.$payment->tenant_id)
            ->andWhere('vendor_payment_id = '.$payment->id);

        // shield saved items from delete query
        if ($ids) {
            $query->andWhere('id NOT IN ('.implode(',', $ids).')');
        }

        $query->executeStatement();
    }
}
