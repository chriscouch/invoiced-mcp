<?php

namespace App\AccountsPayable\Operations;

use App\AccountsPayable\Ledger\AccountsPayableLedger;
use App\AccountsPayable\Models\Vendor;
use App\AccountsPayable\Models\VendorPayment;
use App\Core\Ledger\Exception\LedgerException;
use Carbon\CarbonImmutable;
use App\Core\Orm\Exception\ModelException;

class CreateVendorPayment extends AbstractSaveVendorPayment
{
    public function __construct(
        private readonly AccountsPayableLedger $accountsPayableLedger,
        BillStatusTransition $billStatusTransition,
    ) {
        parent::__construct($billStatusTransition);
    }

    /**
     * Creates a new vendor payment.
     *
     * @throws ModelException
     */
    public function create(array $parameters, array $appliedTo): VendorPayment
    {
        // Create the payment
        $payment = new VendorPayment();

        if (isset($parameters['vendor']) && !$parameters['vendor'] instanceof Vendor) {
            $payment->vendor = Vendor::findOrFail($parameters['vendor']);
            unset($parameters['vendor']);
        }

        if (!isset($parameters['date']) || !$parameters['date']) {
            $parameters['date'] = CarbonImmutable::now();
        }

        if (!isset($parameters['currency']) || !$parameters['currency']) {
            $parameters['currency'] = $payment->tenant()->currency;
        }

        foreach ($parameters as $k => $v) {
            $payment->$k = $v;
        }

        $payment->saveOrFail();

        // Create payment items
        $this->saveItems($payment, $appliedTo);

        $this->validateAppliedAmount($payment);

        // Create the ledger entries
        $ledger = $this->accountsPayableLedger->getLedger($payment->tenant());
        try {
            $this->accountsPayableLedger->syncPayment($ledger, $payment);
            $this->saveRelatedDocumentStatuses($payment);
        } catch (LedgerException $e) {
            throw new ModelException($e->getMessage(), $e->getCode(), $e);
        }

        return $payment;
    }
}
