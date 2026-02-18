<?php

namespace App\Chasing\Legacy;

use App\AccountsReceivable\Enums\InvoiceStatus;
use App\AccountsReceivable\Models\Invoice;
use App\Companies\Models\Company;
use App\Core\Orm\Query;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Sending\Email\Exceptions\SendEmailException;
use App\Sending\Email\Libs\DocumentEmailTemplateFactory;
use App\Sending\Email\Libs\EmailSpool;

/**
 * @deprecated Part of the legacy feature 'legacy_chasing'
 */
class InvoiceChaser implements StatsdAwareInterface
{
    use StatsdAwareTrait;

    public function __construct(private EmailSpool $emailSpool)
    {
    }

    /**
     * Gets all invoices that need to be chased.
     *
     * @return Query<Invoice>
     */
    public function getInvoicesQuery(Company $company): Query
    {
        return Invoice::queryWithTenant($company)
            ->where('status', [
                InvoiceStatus::Voided->value,
                InvoiceStatus::Draft->value,
                InvoiceStatus::Paid->value,
                InvoiceStatus::BadDebt->value,
                InvoiceStatus::Pending->value,
            ], '<>')
            ->where('chase', true)
            ->where('next_chase_on', time(), '<=')
            ->where('next_chase_on', null, '<>')
            ->where('recalculate_chase', false);
    }

    /**
     * Chases an invoice.
     */
    public function chaseInvoice(Invoice $invoice, string $action): bool
    {
        $invoice->skipReconciliation();

        // check if this is a flag chasing step
        if (ChaseScheduleStep::ACTION_FLAG == $action) {
            // NOTE last_sent must be set here to trigger calculation
            // of the next chasing step
            $invoice->needs_attention = true;
            $invoice->last_sent = time();

            return $invoice->save();
        }

        // if email chasing is not allowed for this
        // account then unschedule chasing
        if (!$this->canChaseEmail($invoice)) {
            $invoice->next_chase_on = null;

            return $invoice->save();
        }

        // if we make it down this far then we can
        // actually send the invoice
        try {
            $emailTemplate = (new DocumentEmailTemplateFactory())->get($invoice);
            $this->emailSpool->spoolDocument($invoice, $emailTemplate);
        } catch (SendEmailException) {
            // if an error happens in this job we ignore it
            // because it will be retried in the next job
            // and depending on the error might already be logged
            return false;
        }

        return true;
    }

    /**
     * Checks if the invoice can be chased via email.
     */
    public function canChaseEmail(Invoice $invoice): bool
    {
        $customer = $invoice->customer();

        // verify the customer allows chasing
        if (!$customer->chase) {
            return false;
        }

        // verify the customer has an email address
        $contacts = $customer->emailContacts();

        return count($contacts) > 0;
    }
}
