<?php

namespace App\Chasing\Legacy;

use App\AccountsReceivable\Enums\InvoiceStatus;
use App\AccountsReceivable\Models\Invoice;
use App\Core\Orm\Query;

/**
 * @deprecated Part of the legacy feature 'legacy_chasing'
 */
class InvoiceChasingScheduler
{
    /**
     * Calculates the next chase date for an invoice and chase schedule.
     *
     * @return array [next chase date timestamp, next chase action]
     */
    public function calculateNextChase(Invoice $invoice): array
    {
        $company = $invoice->tenant();
        if (!$company->accounts_receivable_settings->allow_chasing) {
            return [null, null];
        }

        if ($invoice->paid) {
            return [null, null];
        }

        if ($invoice->voided) {
            return [null, null];
        }

        // Only chase AutoPay invoices if:
        // 1) It is past due, or
        // 2) The customer is missing payment information
        if ($invoice->autopay) {
            if (InvoiceStatus::PastDue->value != $invoice->status && $invoice->customer()->payment_source) {
                return [null, null];
            }
        }

        if (InvoiceStatus::Pending->value == $invoice->status) {
            return [null, null];
        }

        if ($invoice->draft) {
            return [null, null];
        }

        if ($invoice->closed) {
            return [null, null];
        }

        if (!$invoice->chase) {
            return [null, null];
        }

        if (!$invoice->customer()->chase) {
            return [null, null];
        }

        $date = $invoice->date;
        $dueDate = $invoice->due_date;
        $lastSent = $invoice->last_sent;

        // calculate the next chase date
        $schedule = ChaseSchedule::get($company);
        $date = 'now' == $date ? time() : $date;
        $nextVal = $schedule->next($date, $dueDate, $lastSent);
        if (!$nextVal) {
            return [null, null];
        }
        [$next, $action] = $nextVal;

        if ($next > 0) {
            $next += 3600;
        }

        // An hour of padding has been added into the chasing
        // schedule calculations so that the invoice is not chased
        // immediately once it is past due, but close to it.
        // This gives the backend some wiggle-room to mark
        // the invoice as past due.
        return [$next, $action];
    }

    /**
     * Gets all the invoices that need to be recalculated.
     *
     * @return Query<Invoice>
     */
    public function getDirtyInvoices(): Query
    {
        return Invoice::queryWithoutMultitenancyUnsafe()
            ->where('status', [
                InvoiceStatus::Voided->value,
                InvoiceStatus::Draft->value,
                InvoiceStatus::Paid->value,
                InvoiceStatus::BadDebt->value,
            ], '<>')
            ->where('chase', true)
            ->where('recalculate_chase', true);
    }
}
