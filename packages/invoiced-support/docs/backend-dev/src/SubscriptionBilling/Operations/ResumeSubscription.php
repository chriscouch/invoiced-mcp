<?php

namespace App\SubscriptionBilling\Operations;

use App\Core\Database\TransactionManager;
use App\SubscriptionBilling\Exception\OperationException;
use App\SubscriptionBilling\Libs\BillingPeriods;
use App\SubscriptionBilling\Libs\DateSnapper;
use App\SubscriptionBilling\Models\Subscription;
use App\SubscriptionBilling\Trait\ModifySubscriptionTrait;
use App\SubscriptionBilling\ValueObjects\BillingPeriod;
use App\SubscriptionBilling\ValueObjects\SubscriptionStatus;
use Carbon\CarbonImmutable;

class ResumeSubscription
{
    use ModifySubscriptionTrait;

    public function __construct(private TransactionManager $transaction)
    {
    }

    /**
     * Resumes a subscription.
     *
     * @throws OperationException
     */
    public function resume(Subscription $subscription, ?CarbonImmutable $desiredPeriodEnd = null): void
    {
        $subscription->tenant()->useTimezone();
        if (!$subscription->paused) {
            throw new OperationException('This subscription cannot be resumed because it is not paused.');
        }

        $subscription->paused = false;
        $this->setStatus($subscription);

        // Calculate and set a new billing period, if needed
        if ($billingPeriod = $this->getBillingPeriod($subscription, $desiredPeriodEnd)) {
            $subscription->setCurrentBillingCycle($billingPeriod);
        }

        $this->transaction->perform(function () use ($subscription) {
            if (!$subscription->save()) {
                if (count($subscription->getErrors()) > 0) {
                    throw new OperationException($subscription->getErrors());
                }

                throw new OperationException('There was an error resuming the subscription.');
            }
        });
    }

    private function getBillingPeriod(Subscription $subscription, ?CarbonImmutable $desiredPeriodEnd): ?BillingPeriod
    {
        if ($desiredPeriodEnd) {
            if ($subscription->snap_to_nth_day > 0) {
                return $this->getBillingPeriodCalendarBilling($subscription, $desiredPeriodEnd);
            }

            return $this->getBillingPeriodAnniversaryBilling($subscription, $desiredPeriodEnd);
        }

        if (CarbonImmutable::now()->greaterThan(CarbonImmutable::createFromTimestamp($subscription->period_end + 1))) {
            $periodStart = CarbonImmutable::now()->setTime(0, 0);
            // The new billing period start cannot be before the current period start
            $periodStart = $periodStart->max(CarbonImmutable::createFromTimestamp((int) $subscription->period_start));
            $periodEnd = BillingPeriods::calculatePeriodEnd($subscription, $periodStart);
            $billDate = $subscription->billingMode()->billDateForPeriod($periodStart, $periodEnd);

            return new BillingPeriod($periodStart, $periodEnd, $billDate);
        }

        return null;
    }

    private function getBillingPeriodAnniversaryBilling(Subscription $subscription, CarbonImmutable $desiredPeriodEnd): BillingPeriod
    {
        $billDate = null;
        $periodEnd = $desiredPeriodEnd;

        // Period End
        // subtract one second to prevent billing period overlap
        if (0 == $periodEnd->getTimestamp() % 2) {
            $periodEnd = $periodEnd->subSecond();
        }

        if ($subscription->period_start > $periodEnd->getTimestamp() && SubscriptionStatus::TRIALING == $subscription->status) {
            $periodEnd = BillingPeriods::calculatePeriodEnd($subscription, CarbonImmutable::createFromTimestamp((int) $subscription->period_start));
        } else {
            $billDate = $subscription->billingMode()->changePeriodEnd($periodEnd);
        }

        $periodStart = $subscription->plan()->interval()->subFrom($periodEnd->getTimestamp());
        $periodStart = CarbonImmutable::createFromTimestamp($periodStart + 1);

        $billDate ??= $subscription->billingMode()->billDateForPeriod($periodStart, $periodEnd);

        // The new billing period start cannot be before the current period start
        $periodStart = $periodStart->max(CarbonImmutable::createFromTimestamp((int) $subscription->period_start));

        return new BillingPeriod($periodStart, $periodEnd, $billDate);
    }

    private function getBillingPeriodCalendarBilling(Subscription $subscription, CarbonImmutable $desiredPeriodEnd): BillingPeriod
    {
        $billDate = null;

        // Snap the desired period end to the natural end of this billing period
        $nthDay = (int) $subscription->snap_to_nth_day;
        $snapper = new DateSnapper($subscription->plan()->interval());
        $periodEnd = $snapper->next($nthDay, $desiredPeriodEnd)->subSecond();

        if ($subscription->period_start > $periodEnd->getTimestamp() && SubscriptionStatus::TRIALING == $subscription->status) {
            $periodEnd = BillingPeriods::calculatePeriodEnd($subscription, CarbonImmutable::createFromTimestamp((int) $subscription->period_start));
        } else {
            $billDate = $subscription->billingMode()->changePeriodEnd($periodEnd);
        }

        $periodStart = $snapper->prev($nthDay, $periodEnd);

        $billDate ??= $subscription->billingMode()->billDateForPeriod($periodStart, $periodEnd);

        // The new billing period start cannot be before the current period start
        $periodStart = $periodStart->max(CarbonImmutable::createFromTimestamp((int) $subscription->period_start));

        return new BillingPeriod($periodStart, $periodEnd, $billDate);
    }
}
