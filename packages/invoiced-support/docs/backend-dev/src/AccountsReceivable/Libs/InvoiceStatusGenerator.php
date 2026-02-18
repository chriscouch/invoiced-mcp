<?php

namespace App\AccountsReceivable\Libs;

use App\AccountsReceivable\Enums\InvoiceStatus;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Models\Transaction;

final class InvoiceStatusGenerator
{
    /**
     * Gets the computed status.
     */
    public static function get(Invoice $invoice): InvoiceStatus
    {
        if ($invoice->voided) {
            return InvoiceStatus::Voided;
        }

        if ($invoice->draft) {
            return InvoiceStatus::Draft;
        }

        if ($invoice->paid) {
            return InvoiceStatus::Paid;
        }

        if ($invoice->closed && $invoice->date_bad_debt) {
            return InvoiceStatus::BadDebt;
        }

        // check for any pending transactions
        $id = $invoice->id();
        if ($id && !$invoice->isFromPendingToFailed()) {
            $n = Transaction::where('invoice', $id)
                ->where('status', Transaction::STATUS_PENDING)
                ->count();
            if ($n > 0) {
                return InvoiceStatus::Pending;
            }
        }

        // AutoPay invoices WITHOUT a due date are past due
        // after the FIRST payment attempt fails
        if (!$invoice->due_date && $invoice->autopay && $invoice->attempt_count > 0) {
            return InvoiceStatus::PastDue;
        }

        // check for any past due installments
        if ($paymentPlan = $invoice->paymentPlan()) {
            foreach ($paymentPlan->installments as $installment) {
                if ($installment->date > time()) {
                    break;
                }

                if ($installment->balance > 0) {
                    return InvoiceStatus::PastDue;
                }
            }
        }

        // all other invoices are past due after due date (if there is one)
        $dueDate = $invoice->due_date;
        if ($dueDate > 0 && $dueDate < time()) {
            return InvoiceStatus::PastDue;
        }

        if ($invoice->viewed) {
            return InvoiceStatus::Viewed;
        }

        if ($invoice->sent) {
            return InvoiceStatus::Sent;
        }

        return InvoiceStatus::NotSent;
    }
}
