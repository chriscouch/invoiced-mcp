<?php

namespace App\SubscriptionBilling\Operations;

use App\AccountsReceivable\Models\Invoice;
use App\Core\Database\TransactionManager;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Core\Utils\AppUrl;
use App\SalesTax\Exception\TaxCalculationException;
use App\Sending\Email\Libs\DocumentEmailTemplateFactory;
use App\Sending\Email\Libs\EmailSpool;
use App\Sending\Email\Libs\EmailTriggers;
use App\SubscriptionBilling\Exception\OperationException;
use App\SubscriptionBilling\Exception\PricingException;
use App\SubscriptionBilling\Libs\SubscriptionInvoice;
use App\SubscriptionBilling\Models\Subscription;
use App\SubscriptionBilling\Trait\ModifySubscriptionTrait;
use Carbon\CarbonImmutable;
use RuntimeException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

/**
 * This class manages the invoice generation process
 * for subscriptions each billing cycle.
 */
class BillSubscription implements StatsdAwareInterface
{
    use StatsdAwareTrait;
    use ModifySubscriptionTrait;

    private ?LockInterface $lock = null;

    public function __construct(
        private LockFactory $lockFactory,
        private CancelSubscription $cancelSubscription,
        private TransactionManager $transaction,
        private EmailSpool $emailSpool
    ) {
    }

    /**
     * Checks if a subscription needs to be billed.
     */
    private function needsBilling(Subscription $subscription): bool
    {
        // check if the subscription is canceled or finished or paused
        $billedNext = $subscription->renews_next;
        if (null === $billedNext || $subscription->canceled || $subscription->paused) {
            return false;
        }

        if (0 == $subscription->bill_in_advance_days) {
            $billedNext = max($subscription->start_date, $billedNext);
        }

        // verify the bill date is not in the future
        if ($billedNext > CarbonImmutable::now()->getTimestamp()) {
            return false;
        }

        return true;
    }

    /**
     * Checks if a subscription should be canceled. This check is
     * supposed to be called before the subscription is billed next.
     */
    private function shouldCancel(Subscription $subscription): bool
    {
        if (!$subscription->cancel_at_period_end) {
            return false;
        }

        // subscriptions with contract terms are not canceled
        // until the end of the contract
        if ($subscription->cycles > 0) {
            return $subscription->num_invoices >= $subscription->cycles;
        }

        return true;
    }

    /**
     * Checks if a subscription has a contract and its term is complete.
     */
    private function contractTermIsComplete(Subscription $subscription): bool
    {
        // The subscription does not have a contract.
        if ($subscription->cycles <= 0) {
            return false;
        }

        // INVD-2881: When a subscription is billed in advance then we do not want
        // to consider the contract complete until the next billing cycle. Otherwise,
        // the contract will be advanced one billing cycle early and the subscription
        // could be canceled one term early.
        // There is one exception to this when a subscription is set to finish at the
        // end of the contract term (contract_renewal_mode=none). The contract is considered
        // ended now in order for it go to a finished status now.
        if (Subscription::BILL_IN_ADVANCE == $subscription->bill_in && Subscription::RENEWAL_MODE_NONE != $subscription->contract_renewal_mode) {
            return $subscription->num_invoices >= ($subscription->cycles + 1);
        }

        return $subscription->num_invoices >= $subscription->cycles;
    }

    /**
     * Checks if a new subscription invoice should be sent.
     */
    public function shouldSendInvoice(Invoice $invoice): bool
    {
        // do not send $0 invoices
        if ($invoice->total <= 0) {
            return false;
        }

        // do not send draft invoices
        if ($invoice->draft) {
            return false;
        }

        // check if the email template has the option enabled
        // to send out new subscription invoices
        if (!EmailTriggers::make($invoice->tenant())->isEnabled('new_subscription_invoice')) {
            return false;
        }

        // do not send an AutoPay invoice when the customer has payment information
        if ($invoice->autopay && $invoice->customer()->payment_source) {
            return false;
        }

        // do not send an invoice more than 32 days old
        if ($invoice->date < (new CarbonImmutable('-32 days'))->getTimestamp()) {
            return false;
        }

        return true;
    }

    /**
     * Generates the next invoice for a subscription.
     * NOTE: this function does not perform any checking if it is
     * actually the right time to generate a new invoice. This
     * makes it possible to generate the next invoice prior to the
     * next time period. The invoice date will be set to the next
     * recurring date and the next recurring date will be set to
     * the invoice date + 1 time period.
     * NOTE the current time is not used at all.
     *
     * @param bool $performCancellations when true, executes any scheduled cancellations
     *
     * @throws OperationException when the invoice cannot be generated due to an underlying exception.
     *                            This does not include subscriptions that cannot be billed when unable to obtain the lock.
     *
     * @return Invoice|null generated invoice or null if the subscription should not be billed
     */
    public function bill(Subscription $subscription, bool $performCancellations = false): ?Invoice
    {
        // Before generating the next invoice get a lock. This
        // prevents other processes from being able to bill this
        // subscription concurrently, which would be a disaster.
        if (!$this->getLock($subscription, 120)) {
            return null;
        }

        // IMPORTANT The subscription must be refreshed after
        // the mutex lock is obtained. Otherwise, the subscription
        // can become stale in-memory if it is billed out-of-band.
        $subscription->refresh();

        // check if the subscription needs to be billed
        if (!$this->needsBilling($subscription)) {
            $this->releaseLock();

            return null;
        }

        // cancel subscriptions that are scheduled for cancellation
        if ($performCancellations && $this->shouldCancel($subscription)) {
            $this->cancelSubscription($subscription);
            $this->releaseLock();

            return null;
        }

        // verify that the plan exists
        try {
            $subscription->plan();
        } catch (RuntimeException) {
            $this->releaseLock();

            throw new OperationException('Subscription invoice could not be generated because the plan does not exist: '.$subscription->plan);
        }

        // build the invoice
        try {
            $invoice = (new SubscriptionInvoice($subscription))->build();
        } catch (TaxCalculationException|PricingException $e) {
            throw new OperationException($e->getMessage(), $e->getCode(), $e);
        }

        // Determine the time at which the subscription invoice was scheduled
        // to be generated. This will be the latest of the renewal date and
        // the creation date (handles backdated subscription start dates).
        $scheduledTime = CarbonImmutable::createFromTimestamp((int) max($subscription->renews_next, $subscription->created_at));

        // save the invoice and advance the subscription within a single transaction
        $this->transaction->perform(function () use ($invoice, $scheduledTime, $subscription) {
            $this->saveSubscriptionInvoice($invoice, $scheduledTime);
            $this->advanceSubscription($subscription, $invoice);
        });

        // send out the new invoice (if turned on)
        if ($this->shouldSendInvoice($invoice)) {
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

        // now that the billing is done the lock should be released
        $this->releaseLock();

        return $invoice;
    }

    /**
     * Save invoice generated from the subscription.
     *
     * @param CarbonImmutable $scheduledTime used to track duration that the subscription invoice has been in queue
     *
     * @throws OperationException
     */
    public function saveSubscriptionInvoice(Invoice $invoice, CarbonImmutable $scheduledTime): Invoice
    {
        $invoice->skipSubscriptionUpdate();
        if (!$invoice->save()) {
            $this->releaseLock();

            throw new OperationException('Could not generate first invoice: '.$invoice->getErrors());
        }

        // tag the time between a subscription invoice being generated
        $this->statsd->increment('subscription.billed');
        $delta = CarbonImmutable::now()->diffInMilliseconds($scheduledTime);
        $this->statsd->timing('subscription.invoice_creation_time', $delta);

        return $invoice;
    }

    /**
     * Handles a subscription post-billing by updating all
     * the various properties.
     *
     * @throws OperationException
     */
    private function advanceSubscription(Subscription $subscription, Invoice $invoice): void
    {
        $subscription->billingPeriods()->advance();
        $subscription->renewed_last = $invoice->date;

        // keep track of how many times this subscription has been billed
        ++$subscription->num_invoices;

        // check if we have exceeded the # of billing cycles
        // and mark the subscription as finished/pending renewal/canceled as appropriate
        if ($this->contractTermIsComplete($subscription)) {
            if ($subscription->cancel_at_period_end) {
                $this->cancelSubscription($subscription);
            } elseif (Subscription::RENEWAL_MODE_NONE == $subscription->contract_renewal_mode) {
                $this->advanceSubscriptionFinishedContract($subscription);
            } elseif (Subscription::RENEWAL_MODE_MANUAL == $subscription->contract_renewal_mode) {
                $this->advanceSubscriptionManualContract($subscription);
            } elseif (in_array($subscription->contract_renewal_mode, [Subscription::RENEWAL_MODE_AUTO, Subscription::RENEWAL_MODE_RENEW_ONCE])) {
                $this->advanceSubscriptionRenewedContract($subscription);
            }
        }

        $this->couponRedemptions($subscription);
        $this->setStatus($subscription);

        // update the subscription
        if (!$subscription->save()) {
            throw new OperationException('Could not advance subscription: '.$subscription->getErrors());
        }
    }

    private function advanceSubscriptionFinishedContract(Subscription $subscription): void
    {
        // subscription is done
        $subscription->finished = true;
        $subscription->clearCurrentBillingCycle();
        $subscription->contract_period_start = null;
        $subscription->contract_period_end = null;
    }

    private function advanceSubscriptionManualContract(Subscription $subscription): void
    {
        // pause the subscription pending renewal or cancellation only if we are not in the current contract period
        $subscription->pending_renewal = $subscription->contract_period_end <= CarbonImmutable::now()->getTimestamp();
        $subscription->renews_next = null;

        // If we reach the end of the contract period then billing modes
        // which bill in the current period must have the contract period
        // advanced here. If this does not happen then the contract period
        // falls behind.
        if (!$subscription->billingMode()->billDateInNextPeriod() && $subscription->pending_renewal) {
            $subscription->contractPeriods()->advance();
        }
    }

    private function advanceSubscriptionRenewedContract(Subscription $subscription): void
    {
        // renew the contract (unless the contract is just starting)
        $isFirstRenewal = $subscription->start_date == $subscription->contract_period_start && 1 == $subscription->num_invoices;
        if (!$isFirstRenewal) {
            $this->renewContract($subscription);
        } else {
            // If we reach the end of the contract period then billing modes
            // which bill in the current period must have the contract period
            // advanced here. If this does not happen then the contract period
            // falls behind.
            if (!$subscription->billingMode()->billDateInNextPeriod()) {
                $subscription->contractPeriods()->advance();
            }
        }
    }

    /**
     * Renews the contract for another term.
     */
    public function renewContract(Subscription $subscription): void
    {
        if (!$subscription->cycles) {
            throw new OperationException('Cannot renew a subscription without a contract.');
        }

        if ($cycles = $subscription->contract_renewal_cycles) {
            $subscription->cycles = $cycles;
        }

        // when renewing a manual contract where the current contract term has not ended yet,
        // set the contract to renew automatically the next time it is up for renewal
        $previousRenewalMode = $subscription->contract_renewal_mode;
        if ($subscription->contract_period_end > CarbonImmutable::now()->getTimestamp() && $subscription->pending_renewal) {
            $subscription->contract_renewal_mode = Subscription::RENEWAL_MODE_RENEW_ONCE;
        }

        // reset the contract
        $subscription->num_invoices = 1; // At this point the first invoice of the contract term has been generated
        $subscription->contractPeriods()->advance();
        $subscription->pending_renewal = false;

        if (Subscription::RENEWAL_MODE_RENEW_ONCE === $previousRenewalMode) {
            $subscription->contract_renewal_mode = Subscription::RENEWAL_MODE_MANUAL;
        }

        // check if the subscription needs a billing date scheduled
        if (!$subscription->renews_next) {
            $subscription->setCurrentBillingCycle($subscription->billingPeriods()->next());
        }
    }

    /**
     * Keep track of how many times each coupon is used.
     */
    public function couponRedemptions(Subscription $subscription): void
    {
        $activeCouponRedemptions = [];
        foreach ($subscription->couponRedemptions() as $redemption) {
            ++$redemption->num_uses;

            // check if the coupon redemption is finished
            $duration = $redemption->coupon()->duration;
            if ($duration > 0 && $redemption->num_uses >= $duration) {
                $redemption->active = false;
            } else {
                // update the model with only the active coupons for
                // MRR calculation purposes.
                $activeCouponRedemptions[] = $redemption;
            }

            $redemption->save();
        }

        $subscription->setCouponRedemptions($activeCouponRedemptions);
    }

    private function cancelSubscription(Subscription $subscription): void
    {
        try {
            $this->cancelSubscription->cancel($subscription);
        } catch (OperationException) {
            // do nothing
        }
    }

    //
    // Mutex Locks
    //

    /**
     * Generates a unique name for a subscription's billing lock.
     */
    private function lockName(Subscription $subscription): string
    {
        return AppUrl::get()->getHostname().':'.
            'sub_bill.'.$subscription->id();
    }

    /**
     * Attempts to get the global lock for a subscription billing.
     *
     * @param float $expires time in which the lock expires
     */
    private function getLock(Subscription $subscription, float $expires = 0): bool
    {
        // do not lock if expiry time is 0
        if ($expires <= 0) {
            return true;
        }

        $k = $this->lockName($subscription);
        $this->lock = $this->lockFactory->createLock($k, $expires);

        return $this->lock->acquire();
    }

    /**
     * Releases the lock for a subscription billing.
     */
    private function releaseLock(): void
    {
        if ($this->lock) {
            $this->lock->release();
            $this->lock = null;
        }
    }
}
