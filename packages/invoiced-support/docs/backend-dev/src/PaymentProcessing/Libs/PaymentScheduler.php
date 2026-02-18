<?php

namespace App\PaymentProcessing\Libs;

use App\AccountsReceivable\Enums\InvoiceStatus;
use App\AccountsReceivable\Models\AccountsReceivableSettings;
use App\AccountsReceivable\Models\Invoice;
use App\PaymentPlans\Models\PaymentPlan;
use App\PaymentProcessing\ValueObjects\RetrySchedule;

class PaymentScheduler
{
    private AccountsReceivableSettings $settings;
    private bool $failed = false;
    private RetrySchedule $retrySchedule;

    public function __construct(private Invoice $invoice)
    {
        $this->settings = $invoice->tenant()->accounts_receivable_settings;
    }

    /**
     * Gets the next payment date for this invoice.
     */
    public function next(): ?int
    {
        // check if this calculation is for a failed attempt
        $failed = $this->failed;
        $this->failed = false;

        $invoice = $this->invoice;
        if (!$invoice->autopay) {
            return null;
        } elseif ($invoice->draft) {
            return null;
        } elseif ($invoice->closed) {
            return null;
        } elseif ($invoice->voided) {
            return null;
        } elseif ($invoice->paid) {
            return null;
        } elseif (InvoiceStatus::Pending->value == $invoice->status) {
            return null;
        }
        if ($failed) {
            return $this->nextFailedAttempt();
        }
        if ($paymentPlan = $invoice->paymentPlan()) {
            return $this->nextPaymentPlan($paymentPlan);
        }

        return $this->nextInvoice();
    }

    /**
     * Tells the scheduler that the next calculation is the result
     * of a failed payment attempt.
     *
     * @return $this
     */
    public function failed()
    {
        $this->failed = true;

        return $this;
    }

    //
    // Helper Methods
    //

    /**
     * Gets the retry schedule for this company.
     */
    private function getRetrySchedule(): RetrySchedule
    {
        if (!isset($this->retrySchedule)) {
            $this->retrySchedule = new RetrySchedule($this->invoice, $this->settings->payment_retry_schedule);
        }

        return $this->retrySchedule;
    }

    /**
     * Gets the timestamp of the next payment attempt for a
     * payment plan.
     *
     * @throws \Exception when something goes wrong
     */
    private function nextPaymentPlan(PaymentPlan $paymentPlan): ?int
    {
        $invoice = $this->invoice;
        // only active payment plans can have scheduled payment attempts
        if (PaymentPlan::STATUS_ACTIVE !== $paymentPlan->status) {
            return null;
        }

        $next = false;

        // when there are failed payment attempts then use it
        // as the starting point for calculating the next attempt.
        if ($invoice->attempt_count > 0) {
            // If the previously failed invoice has no retries
            // scheduled then do not schedule another attempt. When
            // there are no retries scheduled at this point then it
            // means that the automated retry schedule was exhausted.
            $next = $invoice->next_payment_attempt;
            if (!$next) {
                return null;
            }
        }

        // returns the date of the next unpaid installment
        // or the previously scheduled attempt, whichever is later
        foreach ($paymentPlan->installments as $installment) {
            if ($installment->balance > 0) {
                $next = max($next, $installment->date);

                break;
            }
        }
        // something went wrong if there are no unpaid installments
        if (!$next) {
            throw new \Exception('Unable to determine next payment attempt, no unpaid installments');
        }

        return $next;
    }

    /**
     * Gets the timestamp of the next payment attempt for an
     * invoice without a payment plan attached.
     */
    private function nextInvoice(): ?int
    {
        $invoice = $this->invoice;
        // if there is already a next payment date set then
        // use that, or use the existing value
        // when the invoice already has failed attempts
        $next = $invoice->next_payment_attempt;
        if ($next || $invoice->attempt_count > 0 || $invoice->isFromPendingToFailed()) {
            return $next;
        }
        $delay = $this->_calculateInvoiceDelay();
        // convert non numeric values to 0
        // for example "now"
        // if invoice date is not set - we set current timestamp
        $attemptDate = is_numeric($invoice->date) ? (int) $invoice->date : time(); // @phpstan-ignore-line
        $attemptDate += $delay;

        // Schedule the first attempt for an AutoPay invoice
        // without a plan to happen in *at least* 1 hour. The
        // payment attempt will never be scheduled to happen before
        // the invoice date. In most cases the invoice date will be now
        // so effectively the payment attempt is scheduled one hour out.
        // However, if the invoice date is in the future then the
        // invoice will not be collected before that time.
        return max($attemptDate, strtotime('+1 hour'));
    }

    /**
     * @return int - delay seconds
     */
    private function _calculateInvoiceDelay(): int
    {
        $invoice = $this->invoice;
        $customer = $invoice->customer();
        $customerDelay = $customer->autopay_delay_days;
        // if customer delay is set - override default delay
        if (-1 != $customerDelay) {
            return $customerDelay * 86400;
        }
        // otherwise override delay with company setting if set
        $companyDelay = $this->settings->autopay_delay_days;
        if ($companyDelay > 0) {
            return $companyDelay * 86400;
        }

        // by default no delay is set
        return 0;
    }

    /**
     * Gets the next payment attempt after a failed attempt.
     */
    public function nextFailedAttempt(): ?int
    {
        return $this->getRetrySchedule()->next();
    }
}
