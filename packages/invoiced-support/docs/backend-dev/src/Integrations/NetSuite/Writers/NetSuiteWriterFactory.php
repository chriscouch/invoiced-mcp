<?php

namespace App\Integrations\NetSuite\Writers;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\Note;
use App\CashApplication\Models\Payment;
use App\CashApplication\Models\Transaction;
use App\Integrations\AccountingSync\Interfaces\AccountingWritableModelInterface;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;

class NetSuiteWriterFactory
{
    public function create(AccountingWritableModelInterface $record, AccountingSyncProfile $syncProfile): AbstractNetSuiteObjectWriter
    {
        if ($record instanceof Transaction) {
            if (Transaction::TYPE_REFUND === $record->type) {
                return new NetSuiteTransactionRefundWriter($record);
            }

            return new NetSuiteTransactionPaymentWriter($record);
        }
        if ($record instanceof Payment) {
            return new NetSuitePaymentWriter($record);
        }
        if ($record instanceof Note) {
            return new NetSuiteNoteWriter($record);
        }
        if ($record instanceof Customer && $syncProfile->write_customers) {
            return new NetSuiteCustomerWriter($record);
        }
        if ($record instanceof Invoice && $syncProfile->write_invoices) {
            return new NetSuiteInvoiceWriter($record, $syncProfile);
        }
        if ($record instanceof CreditNote && $syncProfile->write_credit_notes) {
            return new NetSuiteCreditNoteWriter($record, $syncProfile);
        }

        return new NullWriterNetSuite($record);
    }
}
