<?php

namespace App\SubscriptionBilling\Libs;

use App\SubscriptionBilling\Interfaces\BillingModeInterface;
use App\SubscriptionBilling\Models\Subscription;
use App\SubscriptionBilling\ValueObjects\BillingPeriod;
use App\SubscriptionBilling\ValueObjects\SubscriptionStatus;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class BillingPeriods
{
    private BillingModeInterface $billingMode;

    public function __construct(private Subscription $subscription)
    {
        $this->subscription->tenant()->useTimezone();
        $this->billingMode = $subscription->billingMode();
    }

    public function getSubscription(): Subscription
    {
        return $this->subscription;
    }

    /**
     * Calculates the initial billing period.
     *
     * @throws InvalidArgumentException
     */
    public function initial(): BillingPeriod
    {
        $currentTime = new CarbonImmutable();

        // Calculate period start and end
        // If the subscription has a future start date
        // then the initial period is from start of today
        // until start date.
        // If the subscription does not have a future start date
        // then the initial period is from the start date to the
        // next scheduled period end date.
        if ($this->subscription->start_date > $currentTime->getTimestamp()) {
            $periodStart = $currentTime->setTime(0, 0);
            $periodEnd = CarbonImmutable::createFromTimestamp($this->subscription->start_date);
        } else {
            [$periodStart, $periodEnd] = self::calculateNextPeriod($this->subscription, CarbonImmutable::createFromTimestamp($this->subscription->start_date));
        }

        // Determine the dates of the first billed period which is after the start date
        [$firstBilledPeriodStart, $firstBilledPeriodEnd] = self::calculateNextPeriod($this->subscription, CarbonImmutable::createFromTimestamp($this->subscription->start_date));

        // Calculate the billing date
        $billDate = $this->billingMode->billDateForPeriod($firstBilledPeriodStart, $firstBilledPeriodEnd);

        // subtract one second to prevent billing period overlap
        if (0 == $periodEnd->getTimestamp() % 2) {
            $periodEnd = $periodEnd->subSecond();
        }

        return new BillingPeriod($periodStart, $periodEnd, $billDate);
    }

    /**
     * Calculates the current billing period for the subscription that is
     * presented on invoices. This might not necessarily be the same as
     * the period_start / period_end values on the subscription.
     */
    public function forUpcomingInvoice(): BillingPeriod
    {
        // If the subscription is behind then we need to
        // advance the current billing period from the stored start/end dates
        if ($this->isBehind()) {
            [$periodStart, $periodEnd] = self::calculateNextPeriod($this->subscription);

            return new BillingPeriod(
                $periodStart,
                $periodEnd,
                $this->subscription->renews_next ? CarbonImmutable::createFromTimestamp($this->subscription->renews_next) : null);
        }

        return $this->current();
    }

    /**
     * Gets the current billing period as stored on the subscription.
     */
    public function current(): BillingPeriod
    {
        return BillingPeriod::fromTimestamps(
            $this->subscription->period_start,
            $this->subscription->period_end,
            $this->subscription->renews_next);
    }

    /**
     * Checks if the billing period should skip ahead.
     */
    public function isBehind(): bool
    {
        if (!$this->billingMode->billDateInNextPeriod()) {
            return SubscriptionStatus::TRIALING == $this->subscription->status;
        }

        // Skip ahead if:
        // 1. In the trial period, or
        // 2. Has been billed before
        return SubscriptionStatus::TRIALING == $this->subscription->status || $this->subscription->renewed_last;
    }

    /**
     * Advances the subscription to the next billing period.
     */
    public function advance(): void
    {
        $this->subscription->setCurrentBillingCycle($this->determinePeriodToAdvance());
    }

    /**
     * Resets the subscription billing period after changing
     * the billing interval of the subscription, i.e. yearly -> monthly.
     */
    public function resetAfterChangedDuration(): void
    {
        $periodStart = CarbonImmutable::now();
        $periodEnd = self::calculatePeriodEnd($this->subscription, $periodStart);
        $billDate = $this->billingMode->billDateForPeriod($periodStart, $periodEnd);
        $billingPeriod = new BillingPeriod($periodStart, $periodEnd, $billDate);
        $this->subscription->setCurrentBillingCycle($billingPeriod);
    }

    /**
     * Gets the billing period that the subscription should advance to. This
     * should be called within the billing process. The result of this function
     * is not necessarily the same as nextBillingPeriod() depending on the state
     * of the subscription.
     */
    public function determinePeriodToAdvance(): BillingPeriod
    {
        if (!$this->billingMode->billDateInNextPeriod()) {
            return $this->next();
        }

        // Check if the billing period should skip ahead
        if ($this->isBehind()) {
            return $this->next();
        }

        // The billing date returned by this function
        // is based on the next billing period not the current.
        [$periodStart, $periodEnd] = BillingPeriods::calculateNextPeriod($this->subscription);
        $billDate = $this->billingMode->billDateForPeriod($periodStart, $periodEnd);

        return new BillingPeriod(
            CarbonImmutable::createFromTimestamp((int) $this->subscription->period_start),
            CarbonImmutable::createFromTimestamp((int) $this->subscription->period_end),
            $billDate
        );
    }

    /**
     * Calculates the next billing period.
     */
    public function next(): BillingPeriod
    {
        if (!$this->billingMode->billDateInNextPeriod()) {
            [$periodStart, $periodEnd] = BillingPeriods::calculateNextPeriod($this->subscription);
            $billDate = $this->billingMode->billDateForPeriod($periodStart, $periodEnd);

            // special case for initial trial
            // billing period needs to catch up with subscription
            $status = new SubscriptionStatus($this->subscription);
            if (SubscriptionStatus::TRIALING == $status->get()) {
                $periodStart = $periodEnd->addSecond();
                $periodEnd = BillingPeriods::calculatePeriodEnd($this->subscription, $periodStart);
                $billDate = $this->billingMode->billDateForPeriod($periodStart, $periodEnd);
            }

            return new BillingPeriod($periodStart, $periodEnd, $billDate);
        }

        [$periodStart, $periodEnd] = BillingPeriods::calculateNextPeriod($this->subscription);
        // The billing date is based on the billing period after the next.
        [$periodStart2, $periodEnd2] = BillingPeriods::calculateNextPeriod($this->subscription, $periodEnd->addSecond());
        $billDate = $this->billingMode->billDateForPeriod($periodStart2, $periodEnd2);

        return new BillingPeriod($periodStart, $periodEnd, $billDate);
    }

    /**
     * Gets the human-readable # of days until the subscription is billed.
     *
     * This calculates the # of day boundaries until the next
     * bill date, relative to the company's time zone.
     * i.e. if it was 11:59pm and a subscription billed
     * at 12am the day after next then the result would be 2 days
     * (even though it is closer to 1 day).
     */
    public function billsIn(): string
    {
        $billDate = $this->subscription->renews_next;
        if (!$billDate) {
            return '';
        }

        $currentTime = CarbonImmutable::now()->getTimestamp();

        // get the minimum # of days until billing
        $delta = $billDate - $currentTime;
        $days = floor($delta / 86400);

        // check if the remainder crosses a date boundary
        // by checking if the days of month are different
        $remainder = $delta % 86400;
        $currentTime = $billDate - $remainder;
        if (date('d', $currentTime) !== date('d', $billDate)) {
            ++$days;
        }

        if ($days <= 0) {
            return 'less than 1 day';
        }

        return $days.((1 != $days) ? ' days' : ' day');
    }

    /**
     * Calculates the percent time remaining in a billing period.
     *
     * @return float percentage (between 0 and 1)
     */
    public function percentTimeRemaining(?CarbonImmutable $currentTime): float
    {
        $currentTime ??= new CarbonImmutable();
        $currentTime = $currentTime->getTimestamp();

        if (SubscriptionStatus::TRIALING == $this->subscription->status) {
            $end = $this->forUpcomingInvoice()->getEndDateTimestamp();
        } else {
            $end = $this->subscription->period_end;
        }

        // if the period ended in the past then return 0%
        if ($end < $currentTime) {
            return 0;
        }

        // compute the period start as a full period before period end
        // NOTE: this is not necessarily the period's actual start
        // date if calendar billing was used, or the bill date
        // was changed for any reason
        $interval = $this->subscription->plan()->interval();
        $start = $interval->subFrom($end + 1);

        // compute what percentage remains of the billing period
        $timeRemaining = $end - $currentTime;
        $totalTime = $end - $start;

        // ensure we can never exceed 100%
        return min(1, $timeRemaining / $totalTime);
    }

    /**
     * Computes the date of the end of the last billing period
     * for the subscription, if this is not an evergreen subscription.
     */
    public function endDate(): ?CarbonImmutable
    {
        $cycles = $this->subscription->cycles;

        // handles subscriptions with contract terms
        if ($cycles > 0) {
            // contracts that are scheduled to renew do not have an end date
            if (!$this->subscription->cancel_at_period_end
                && in_array(
                    $this->subscription->contract_renewal_mode,
                    [Subscription::RENEWAL_MODE_AUTO, Subscription::RENEWAL_MODE_RENEW_ONCE]
                )) {
                return null;
            }

            // calculate the end date as when the last bill will
            // happen, which is going to be one interval before the contract period ends
            // since customers are billed up front
            if ($start = $this->subscription->contract_period_start) {
                $endDate = $start;
            } else {
                $endDate = $this->subscription->start_date;
            }
            $interval = $this->subscription->plan()->interval();

            // Add an additional period to get the start date of the billing period
            // after the subscription is finished.
            for ($i = 0; $i < $cycles; ++$i) {
                $endDate = $interval->addTo($endDate);
            }

            // Then subtract one second to get the true end date.
            return CarbonImmutable::createFromTimestamp($endDate - 1);
        }

        // handles subscriptions canceled at the end of the billing period (except contracts)
        if (!$this->subscription->canceled && $this->subscription->cancel_at_period_end) {
            return CarbonImmutable::createFromTimestamp((int) $this->subscription->period_end);
        }

        return null;
    }

    /**
     * @return CarbonImmutable[]
     */
    public static function calculateNextPeriod(Subscription $subscription, ?CarbonImmutable $periodStart = null): array
    {
        // the beginning of the period is one second after the last period
        // if no start date is given
        $periodStart ??= CarbonImmutable::createFromTimestamp($subscription->period_end + 1);

        return [
            $periodStart,
            self::calculatePeriodEnd($subscription, $periodStart),
        ];
    }

    /**
     * Advances to the next billing period end date, taking into account calendar billing if used.
     */
    public static function advanceToNextSnap(Subscription $subscription, CarbonImmutable $date): CarbonImmutable
    {
        $nthDay = $subscription->snap_to_nth_day;
        if (!$nthDay) {
            return $date;
        }
        $interval = $subscription->plan()->interval();
        $snapper = new DateSnapper($interval);

        $endDate = $snapper->next($nthDay, $date);

        // INVD-1374: Fixes an issue where the period start date
        // and nth day of the month are slightly off due to a timezone
        // delta. For example, if a user's clock is set to CST and the business time zone
        // is MST then when the subscription is created with date snapping the period
        // end can be off by one interval. This check ensures that the billing period is
        // at least one day in length, which is one way to detect this bug. The solution
        // is to add one more interval to the end date.
        if ($endDate->getTimestamp() - $date->getTimestamp() < 86400) {
            $endDate = $snapper->next($nthDay, $endDate);
        }

        return $endDate->subSecond();
    }

    /**
     * Calculates the period end date based on the start date provided.
     */
    public static function calculatePeriodEnd(Subscription $subscription, CarbonImmutable $periodStart): CarbonImmutable
    {
        // Calendar Billing Mode
        $nthDay = $subscription->snap_to_nth_day;
        if ($nthDay > 0) {
            return self::advanceToNextSnap($subscription, $periodStart);
        }

        $interval = $subscription->plan()->interval();
        // Anniversary Billing Mode
        $endDate = $interval->addTo($periodStart->getTimestamp());

        return CarbonImmutable::createFromTimestamp($endDate - 1);
    }
}
