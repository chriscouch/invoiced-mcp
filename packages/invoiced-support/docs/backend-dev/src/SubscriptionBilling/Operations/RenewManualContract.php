<?php

namespace App\SubscriptionBilling\Operations;

use App\AccountsReceivable\Models\Invoice;
use App\Core\Database\TransactionManager;
use App\SalesTax\Exception\TaxCalculationException;
use App\Sending\Email\Libs\DocumentEmailTemplateFactory;
use App\Sending\Email\Libs\EmailSpool;
use App\SubscriptionBilling\Exception\OperationException;
use App\SubscriptionBilling\Exception\PricingException;
use App\SubscriptionBilling\Libs\SubscriptionInvoice;
use App\SubscriptionBilling\Models\Subscription;
use App\SubscriptionBilling\ValueObjects\SubscriptionStatus;
use Carbon\CarbonImmutable;

class RenewManualContract
{
    public function __construct(private BillSubscription $billSubscription, private TransactionManager $transaction, private EmailSpool $emailSpool)
    {
    }

    /**
     * Performs the renewal of a subscription with a manual
     * contract renewal mode.
     *
     * @throws OperationException
     */
    public function renew(Subscription $subscription, int $cycles): Invoice
    {
        // Only subscriptions with a manual renewal mode can be renewed
        if (Subscription::RENEWAL_MODE_MANUAL != $subscription->contract_renewal_mode) {
            throw new OperationException('Only contracts belonging to subscriptions that require approval can be renewed.');
        }

        if (!$cycles) {
            throw new OperationException('Cannot renew a subscription without a contract.');
        }

        $subscription->cycles = $cycles;

        // If the bill date is in the next period then the billing period
        // must be advanced BEFORE the subscription invoice is created in
        // order to get accurate billing period and invoice dates.
        if ($subscription->billingMode()->billDateInNextPeriod()) {
            $subscription->billingPeriods()->advance();
        }
        // update contract period
        $subscription->contractPeriods()->advance();
        // we always bill outdated pending review subscription
        if (SubscriptionStatus::PENDING_RENEWAL === $subscription->status && $subscription->contract_period_end > time()) {
            $subscription->status = SubscriptionStatus::ACTIVE;
            $subscription->pending_renewal = false;
        }

        $invoice = $this->makeInvoice($subscription);

        $this->transaction->perform(function () use ($invoice, $subscription) {
            $invoice = $this->billSubscription->saveSubscriptionInvoice($invoice, new CarbonImmutable());

            // If the bill date is not in the next period then the billing period
            // must be advanced AFTER the subscription invoice is created in
            // order to get accurate billing period and invoice dates.
            if (!$subscription->billingMode()->billDateInNextPeriod()) {
                $subscription->billingPeriods()->advance();
            }
            $subscription->renews_next = null;
            $subscription->renewed_last = $invoice->date;
            $subscription->num_invoices = 1; // At this point the first invoice of the contract term has been generated

            $this->billSubscription->couponRedemptions($subscription);

            $subscription->save();
        });

        // send out the new invoice (if turned on)
        if ($this->billSubscription->shouldSendInvoice($invoice)) {
            $emailTemplate = (new DocumentEmailTemplateFactory())->get($invoice);
            // If the invoice email fails to spool then we don't
            // pass along the error because we want the operation
            // to succeed. The invoice will have a status of not_sent.
            $this->emailSpool->spoolDocument($invoice, $emailTemplate, [], false);
        }

        // Calculate MRR outside the database transaction because
        // it should not affect the outcome of the subscription advancing.
        // This can result in external callouts like for sales tax calculation.
        $subscription->updateMrr();

        return $invoice;
    }

    /**
     * Builds the next subscription invoice.
     *
     * @throws OperationException
     */
    private function makeInvoice(Subscription $subscription): Invoice
    {
        // build the invoice
        // temporary assign renew next to properly generate the Invoice
        $billDate = $subscription->billingMode()->billDateForPeriod(
            CarbonImmutable::createFromTimestamp((int) $subscription->period_start),
            CarbonImmutable::createFromTimestamp((int) $subscription->period_end));
        $subscription->renews_next = $billDate->getTimestamp();
        // build the next invoice in the series
        try {
            $invoice = (new SubscriptionInvoice($subscription))->build();
        } catch (TaxCalculationException|PricingException $e) {
            throw new OperationException($e->getMessage(), $e->getCode(), $e);
        }
        // fix line items of the invoice
        $start = $subscription->period_start;
        $end = $subscription->period_end;
        $invoice->items = array_map(function ($item) use ($start, $end) {
            $item['period_start'] = $start;
            $item['period_end'] = $end;

            return $item;
        }, $invoice->items);
        // rollback temporary assigment
        $subscription->renews_next = null;

        return $invoice;
    }
}
