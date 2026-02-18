<?php

namespace App\Integrations\Xero\Writers;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Models\Payment;
use App\Integrations\AccountingSync\Interfaces\AccountingWritableModelInterface;
use App\Integrations\AccountingSync\Interfaces\AccountingWriterInterface;
use App\Integrations\AccountingSync\Writers\NullWriter;

class XeroWriterFactory
{
    public function __construct(
        private XeroCustomerWriter $customers,
        private XeroCreditNoteWriter $creditNotes,
        private XeroInvoiceWriter $invoices,
        private XeroPaymentWriter $payments,
    ) {
    }

    public function get(AccountingWritableModelInterface $model): AccountingWriterInterface
    {
        return match (get_class($model)) {
            Customer::class => $this->customers,
            Invoice::class => $this->invoices,
            Payment::class => $this->payments,
            CreditNote::class => $this->creditNotes,
            default => new NullWriter(),
        };
    }
}
