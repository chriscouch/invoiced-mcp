<?php

namespace App\SubscriptionBilling\Operations;

use App\Core\Database\TransactionManager;
use App\SubscriptionBilling\Exception\OperationException;
use App\SubscriptionBilling\Libs\BillingPeriods;
use App\SubscriptionBilling\Libs\Proration;
use App\SubscriptionBilling\Models\Subscription;
use App\SubscriptionBilling\Trait\ModifySubscriptionTrait;
use App\SubscriptionBilling\ValueObjects\SubscriptionStatus;
use Carbon\CarbonImmutable;
use App\Core\Orm\Exception\MassAssignmentException;

class EditSubscription
{
    use ModifySubscriptionTrait;

    public function __construct(
        private TransactionManager $transaction,
        private BillSubscription $billSubscription,
        private PauseSubscription $pauseSubscription,
        private ResumeSubscription $resumeSubscription,
    ) {
    }

    /**
     * Performs a modification of a trialing or active subscription.
     *
     * @throws OperationException
     */
    public function modify(Subscription $subscription, array $parameters): void
    {
        // block modifying a canceled subscription
        if (isset($parameters['canceled']) && $subscription->canceled) {
            throw new OperationException('Canceled subscriptions cannot be reactivated. Please create a new subscription instead.');
        }

        // Pause/resume subscription
        // Performing these operations through an edit operation is supported
        // for backwards compatibility but is not recommended.
        if ($this->doPauseOrResume($subscription, $parameters)) {
            return;
        }

        if (isset($parameters['customer'])) {
            throw new OperationException('Invalid request parameter `customer`. The customer cannot be modified.');
        }

        $this->verifyPlan($subscription, $parameters);
        $this->verifyAddons($subscription, $parameters);
        $this->verifyCoupons($subscription, $parameters);
        $this->verifyContractRenewalMethod($subscription, $parameters);
        $this->verifyDates($subscription, $parameters);
        $this->verifySubscriptionAmount($subscription, $parameters);

        $prorationDate = $this->parseProrationDate($parameters);

        // load the existing payment source
        // NOTE even if not it will not be deleted this is
        // necessary in order to generate the correct previous
        // value for the subscription.updated event
        $oldSource = $subscription->payment_source;

        // Load the before state for use with prorations
        $before = Subscription::findOrFail($subscription->id());
        $before->plan();
        $before->loadAddons();

        // Period End
        if (isset($parameters['period_end'])) {
            $parameters['period_end'] = $this->parsePeriodEnd($subscription, $parameters['period_end']);
        }

        foreach ($parameters as $k => $v) {
            $subscription->$k = $v;
        }

        $this->verifyBillInAdvanceDays($subscription);
        $this->calculateContractPeriod($subscription);
        $this->setStatus($subscription);

        // update the start date if extending the trial
        if (SubscriptionStatus::TRIALING == $subscription->status && $subscription->dirty('renews_next')) {
            $subscription->start_date = (int) $subscription->renews_next;
        }

        $this->transaction->perform(function () use ($subscription, $before, $prorationDate) {
            // Modify the subscription model
            try {
                if (!$subscription->set()) {
                    if (count($subscription->getErrors()) > 0) {
                        throw new OperationException($subscription->getErrors());
                    }

                    throw new OperationException('There was an error modifying the subscription.');
                }
            } catch (MassAssignmentException $e) {
                throw new OperationException($e->getMessage());
            }

            // Apply prorations
            $this->applyProration($subscription, $before, $prorationDate);
        });

        // Recalculate MRR / Recurring Total
        $subscription->updateMrr();
    }

    private function doPauseOrResume(Subscription $subscription, array $parameters): bool
    {
        if (!isset($parameters['paused'])) {
            return false;
        }

        if ($parameters['paused'] && !$subscription->paused) {
            $this->pauseSubscription->pause($subscription);

            return true;
        }

        if (!$parameters['paused'] && $subscription->paused) {
            $periodEnd = null;
            if (isset($parameters['period_end'])) {
                $periodEnd = CarbonImmutable::createFromTimestamp($parameters['period_end']);
            }

            $this->resumeSubscription->resume($subscription, $periodEnd);

            return true;
        }

        return false;
    }

    private function parsePeriodEnd(Subscription $subscription, int $periodEnd): int
    {
        // subtract one second to prevent billing period overlap
        if (0 == $periodEnd % 2) {
            $periodEnd = $periodEnd - 1;
        }

        if (SubscriptionStatus::TRIALING == $subscription->status) {
            $periodEndDate = CarbonImmutable::createFromTimestamp($periodEnd);
            // today or past
            $today = CarbonImmutable::now();
            if (floor($periodEndDate->diffInDays($today)) <= 0) {
                $startOfToday = $today->startOfDay();
                $subscription->start_date = $subscription->period_start = $startOfToday->getTimestamp();
                $subscription->renews_next = $today->addHour()->getTimestamp();

                return BillingPeriods::calculatePeriodEnd($subscription, $startOfToday)->getTimestamp();
            }

            if ($subscription->period_start > $periodEnd) {
                $subscription->renews_next = $subscription->period_start;

                return BillingPeriods::calculatePeriodEnd($subscription, CarbonImmutable::createFromTimestamp((int) $subscription->period_start))->getTimestamp();
            }
        }

        $subscription->renews_next = $subscription->billingMode()->changePeriodEnd(CarbonImmutable::createFromTimestamp($periodEnd))->getTimestamp();

        return $periodEnd;
    }

    private function parseProrationDate(array &$parameters): CarbonImmutable
    {
        // Prorate by default
        $parameters['prorate'] ??= true;

        // Determine proration date
        if (isset($parameters['proration_date'])) {
            $prorationDate = CarbonImmutable::createFromTimestamp($parameters['proration_date']);
            unset($parameters['proration_date']);
        } else {
            $prorationDate = CarbonImmutable::now();
        }

        return $prorationDate;
    }

    /**
     * Applies prorations to the subscription after modifying it.
     *
     * @throws OperationException
     */
    private function applyProration(Subscription $subscription, Subscription $before, ?CarbonImmutable $prorationDate): void
    {
        $proration = new Proration($before, $subscription, $prorationDate);

        // apply the proration if enabled for this change
        if ($subscription->prorate) {
            $proration->apply();
        }

        // start a new billing cycle now if changing duration
        if ($proration->changedCycle()) {
            $subscription->snap_to_nth_day = null; // INVD-2588: snap_to_nth_day accepts different values per interval
            $subscription->billingPeriods()->resetAfterChangedDuration();
            $subscription->prorate = false;
            // INVD-2588: The new billing cycle hasn't been renewed.
            // The billing periods should not advance until
            // after the first invoice w/ this new billing
            // cycle is issued.
            $subscription->renewed_last = null;
            $subscription->save();

            $this->billSubscription->bill($subscription);
        }
    }

    private function verifyDates(Subscription $subscription, array $parameters): void
    {
        // can only set start date when accepting subscription
        if (isset($parameters['start_date']) && SubscriptionStatus::TRIALING != $subscription->status) {
            throw new OperationException('The start date cannot be modified for an active subscription');
        }

        // handle cancellations at the end of the billing period
        if (isset($parameters['cancel_at_period_end']) && !$subscription->cancel_at_period_end) {
            if ($parameters['cancel_at_period_end']) {
                $subscription->canceled_at = CarbonImmutable::now()->getTimestamp();
            } else {
                $subscription->canceled_at = null;
            }
        }
    }
}
