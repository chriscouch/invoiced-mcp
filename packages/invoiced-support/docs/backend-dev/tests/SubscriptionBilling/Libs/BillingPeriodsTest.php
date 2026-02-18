<?php

namespace App\Tests\SubscriptionBilling\Libs;

use App\SubscriptionBilling\Models\Plan;
use App\Core\Utils\ValueObjects\Interval;
use App\SubscriptionBilling\Libs\BillingPeriods;
use App\SubscriptionBilling\Models\Subscription;
use App\SubscriptionBilling\ValueObjects\SubscriptionStatus;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;

class BillingPeriodsTest extends AppTestCase
{
    private static CarbonImmutable $startOfToday;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        date_default_timezone_set('UTC');
        self::hasCompany();

        // enable manual contract renewals for testing
        self::$company->features->enable('subscription_manual_renewal');

        self::$startOfToday = (new CarbonImmutable())->setTime(0, 0);
    }

    private function getBillingPeriods(?Subscription $subscription = null): BillingPeriods
    {
        return new BillingPeriods($subscription ?? $this->getSubscription());
    }

    private function getSubscription(): Subscription
    {
        $subscription = new Subscription();
        $subscription->tenant_id = (int) self::$company->id();

        $plan = new Plan();
        $plan->interval = Interval::MONTH;
        $plan->interval_count = 1;
        $subscription->setPlan($plan);

        $interval = $subscription->plan()->interval();

        $subscription->start_date = self::$startOfToday->getTimestamp();
        $subscription->period_start = self::$startOfToday->getTimestamp();
        $subscription->period_end = $interval->addTo($subscription->period_start) - 1;
        $subscription->renews_next = self::$startOfToday->getTimestamp();
        $subscription->renewed_last = null;

        return $subscription;
    }

    public function testGetSubscription(): void
    {
        $period = $this->getBillingPeriods();
        $subscription = $period->getSubscription();
        $this->assertInstanceOf(Subscription::class, $subscription);
    }

    public function testInitial(): void
    {
        // Check with a future dated subscription
        $billingPeriods = $this->getBillingPeriods();
        $subscription = $billingPeriods->getSubscription();
        $subscription->start_date = (int) mktime(0, 0, 0, 10, 1, 2019);
        CarbonImmutable::setTestNow('2019-09-01T07:00:00Z');
        $billingPeriod = $billingPeriods->initial();
        $this->assertEquals(
            new CarbonImmutable('2019-10-01'),
            $billingPeriod->billDate,
            'Could not verify bill date of initial period'
        );
        $this->assertEquals(
            new CarbonImmutable('2019-09-01'),
            $billingPeriod->startDate,
            'Could not verify start date of initial period'
        );
        $this->assertEquals(
            new CarbonImmutable('2019-09-30T23:59:59Z'),
            $billingPeriod->endDate,
            'Could not verify end date of initial period'
        );

        // Check with a subscription starting in the past
        $billingPeriods = $this->getBillingPeriods();
        $subscription = $billingPeriods->getSubscription();
        $subscription->start_date = (int) mktime(0, 0, 0, 7, 1, 2019);
        CarbonImmutable::setTestNow('2019-09-01T07:00:00Z');
        $billingPeriod = $billingPeriods->initial();
        $this->assertEquals(
            new CarbonImmutable('2019-07-01'),
            $billingPeriod->billDate,
            'Could not verify bill date of initial period'
        );
        $this->assertEquals(
            new CarbonImmutable('2019-07-01'),
            $billingPeriod->startDate,
            'Could not verify start date of initial period'
        );
        $this->assertEquals(
            new CarbonImmutable('2019-07-31T23:59:59Z'),
            $billingPeriod->endDate,
            'Could not verify end date of initial period'
        );

        // Check with calendar billing
        $billingPeriods = $this->getBillingPeriods();
        $subscription = $billingPeriods->getSubscription();
        $subscription->start_date = (int) mktime(0, 0, 0, 7, 7, 2019);
        $subscription->snap_to_nth_day = 1;
        CarbonImmutable::setTestNow('2019-09-01T07:00:00Z');
        $billingPeriod = $billingPeriods->initial();
        $this->assertEquals(
            new CarbonImmutable('2019-07-07'),
            $billingPeriod->billDate,
            'Could not verify bill date of initial period'
        );
        $this->assertEquals(
            new CarbonImmutable('2019-07-07'),
            $billingPeriod->startDate,
            'Could not verify start date of initial period'
        );
        $this->assertEquals(
            new CarbonImmutable('2019-07-31T23:59:59Z'),
            $billingPeriod->endDate,
            'Could not verify end date of initial period'
        );
    }

    public function testCurrent(): void
    {
        $subscription = new Subscription();
        $subscription->period_start = 1;
        $subscription->period_end = 2;
        $subscription->renews_next = 3;
        $period = $this->getBillingPeriods($subscription);

        $billingPeriod = $period->forUpcomingInvoice();
        $this->assertEquals(
            new CarbonImmutable('@3'),
            $billingPeriod->billDate,
            'Could not verify bill date of current period'
        );
        $this->assertEquals(
            new CarbonImmutable('@1'),
            $billingPeriod->startDate,
            'Could not verify start date of current period'
        );
        $this->assertEquals(
            new CarbonImmutable('@2'),
            $billingPeriod->endDate,
            'Could not verify end date of current period'
        );
    }

    public function testForUpcomingInvoice(): void
    {
        $period = $this->getBillingPeriods();

        $billingPeriod = $period->forUpcomingInvoice();
        $this->assertEquals(
            self::$startOfToday,
            $billingPeriod->billDate,
            'Could not verify bill date of current period'
        );
        $this->assertEquals(
            self::$startOfToday,
            $billingPeriod->startDate,
            'Could not verify start date of current period'
        );
        $interval = new Interval(1, Interval::MONTH);
        $expectedEndDate = CarbonImmutable::createFromTimestamp($interval->addTo(self::$startOfToday->getTimestamp()) - 1);
        $this->assertEquals(
            $expectedEndDate,
            $billingPeriod->endDate,
            'Could not verify end date of current period'
        );
    }

    public function testForUpcomingInvoiceTrialing(): void
    {
        $period = $this->getBillingPeriods();
        $subscription = $period->getSubscription();
        $subscription->start_date = $subscription->period_end + 1;
        $subscription->renews_next = $subscription->start_date;
        $subscription->status = SubscriptionStatus::TRIALING;

        $billingPeriod = $period->forUpcomingInvoice();
        $interval = $subscription->plan()->interval();
        $this->assertEquals(
            CarbonImmutable::createFromTimestamp($interval->addTo(self::$startOfToday->getTimestamp())),
            $billingPeriod->billDate,
            'Could not verify bill date of current period'
        );
        $this->assertEquals(
            CarbonImmutable::createFromTimestamp($subscription->start_date),
            $billingPeriod->startDate,
            'Could not verify start date of current period'
        );
        $interval = $subscription->plan()->interval();
        $this->assertEquals(
            CarbonImmutable::createFromTimestamp($interval->addTo($subscription->start_date) - 1),
            $billingPeriod->endDate,
            'Could not verify end date of current period'
        );
    }

    public function testForUpcomingInvoiceDaysInAdvance(): void
    {
        $period = $this->getBillingPeriods();
        $subscription = $period->getSubscription();
        $subscription->bill_in_advance_days = 7;

        $billingPeriod = $period->forUpcomingInvoice();
        $this->assertEquals(
            self::$startOfToday,
            $billingPeriod->billDate,
            'Could not verify bill date of current period'
        );
        $this->assertEquals(
            self::$startOfToday,
            $billingPeriod->startDate,
            'Could not verify start date of current period'
        );
        $interval = $subscription->plan()->interval();

        $this->assertEquals(
            CarbonImmutable::createFromTimestamp($interval->addTo($subscription->start_date) - 1),
            $billingPeriod->endDate,
            'Could not verify end date of current period'
        );
    }

    public function testIsBehind(): void
    {
        $subscription = new Subscription();
        $period = $this->getBillingPeriods($subscription);
        $this->assertFalse($period->isBehind());

        $subscription = new Subscription(['status' => SubscriptionStatus::TRIALING]);
        $period = $this->getBillingPeriods($subscription);
        $this->assertTrue($period->isBehind());

        $subscription = new Subscription(['renewed_last' => time()]);
        $period = $this->getBillingPeriods($subscription);
        $this->assertTrue($period->isBehind());
    }

    public function testNextAdvance(): void
    {
        $subscription = $this->getSubscription();
        $billingPeriods = $this->getBillingPeriods($subscription);

        $billingPeriod = $billingPeriods->next();
        /** @var CarbonImmutable $startDate */
        $startDate = $billingPeriod->startDate;
        $interval = $subscription->plan()->interval();
        $this->assertEquals(
            CarbonImmutable::createFromTimestamp($interval->addTo((int) $startDate->timestamp)),
            $billingPeriod->billDate,
            'Could not verify bill date of next period'
        );
        $this->assertEquals(
            CarbonImmutable::createFromTimestamp($interval->addTo(self::$startOfToday->getTimestamp())),
            $billingPeriod->startDate,
            'Could not verify start date of next period'
        );
        $this->assertEquals(
            CarbonImmutable::createFromTimestamp($interval->addTo((int) $startDate->timestamp) - 1),
            $billingPeriod->endDate,
            'Could not verify end date of next period'
        );
    }

    public function testNextDaysInAdvance(): void
    {
        $subscription = $this->getSubscription();
        $subscription->bill_in_advance_days = 7;
        $billingPeriods = $this->getBillingPeriods($subscription);
        $interval = $subscription->plan()->interval();
        $billingPeriod = $billingPeriods->next();
        /** @var CarbonImmutable $startDate */
        $startDate = $billingPeriod->startDate;
        $this->assertEquals(
            CarbonImmutable::createFromTimestamp($interval->addTo((int) $startDate->timestamp))
                ->modify('-7 days'),
            $billingPeriod->billDate,
            'Could not verify bill date of next period with bill in advance offset'
        );
        $this->assertEquals(
            CarbonImmutable::createFromTimestamp($interval->addTo(self::$startOfToday->getTimestamp())),
            $billingPeriod->startDate,
            'Could not verify start date of next period with bill in advance offset'
        );
        $this->assertEquals(
            CarbonImmutable::createFromTimestamp($interval->addTo((int) $startDate->timestamp) -1),
            $billingPeriod->endDate,
            'Could not verify end date of next period with bill in advance offset'
        );
    }

    public function testNextArrearsWithTrial(): void
    {
        $subscription = $this->getSubscription();
        $subscription->bill_in = Subscription::BILL_IN_ARREARS;
        $subscription->start_date = (int) mktime(0, 0, 0, 9, 1, 2019);
        $subscription->period_start = (int) mktime(0, 0, 0, 8, 20, 2019);
        $subscription->period_end = (int) mktime(23, 59, 59, 8, 31, 2019);
        $subscription->renews_next = (int) mktime(23, 59, 59, 8, 31, 2019);
        $billingPeriods = $this->getBillingPeriods($subscription);

        // If there is a trial then the subscription must catch up with two cycles
        $billingPeriod = $billingPeriods->next();
        $this->assertEquals(
            new CarbonImmutable('2019-10-31T23:59:59Z'),
            $billingPeriod->billDate,
            'Could not verify bill date of next period'
        );
        $this->assertEquals(
            new CarbonImmutable('2019-10-01'),
            $billingPeriod->startDate,
            'Could not verify start date of next period'
        );
        $this->assertEquals(
            new CarbonImmutable('2019-10-31T23:59:59Z'),
            $billingPeriod->endDate,
            'Could not verify end date of next period'
        );
    }

    public function testNextArrearsNoTrial(): void
    {
        $subscription = $this->getSubscription();
        $subscription->bill_in = Subscription::BILL_IN_ARREARS;
        $billingPeriods = $this->getBillingPeriods($subscription);
        $interval = $subscription->plan()->interval();
        // Without a trial then the period should be advanced by one cycle
        $billingPeriod = $billingPeriods->next();
        /** @var CarbonImmutable $startDate */
        $startDate = $billingPeriod->startDate;
        $this->assertEquals(
            CarbonImmutable::createFromTimestamp($interval->addTo((int) $startDate->timestamp) - 1),
            $billingPeriod->billDate,
            'Could not verify bill date of next period'
        );
        $this->assertEquals(
            CarbonImmutable::createFromTimestamp($interval->addTo(self::$startOfToday->getTimestamp())),
            $billingPeriod->startDate,
            'Could not verify start date of next period'
        );
        $this->assertEquals(
            CarbonImmutable::createFromTimestamp($interval->addTo((int) $startDate->timestamp) - 1),
            $billingPeriod->endDate,
            'Could not verify end date of next period'
        );
    }

    public function testDeterminePeriodToAdvance(): void
    {
        // Check when subscription is in trial period
        $subscription = $this->getSubscription();
        $subscription->status = SubscriptionStatus::TRIALING;
        $subscription->start_date = (int) mktime(0, 0, 0, 9, 1, 2019);
        $subscription->period_start = (int) mktime(0, 0, 0, 8, 1, 2019);
        $subscription->period_end = (int) mktime(23, 59, 59, 8, 31, 2019);
        $billingPeriod = $this->getBillingPeriods($subscription)->determinePeriodToAdvance();
        $this->assertEquals(
            new CarbonImmutable('2019-10-01'),
            $billingPeriod->billDate,
            'Could not verify bill date of advance period'
        );
        $this->assertEquals(
            new CarbonImmutable('2019-09-01'),
            $billingPeriod->startDate,
            'Could not verify start date of advance period'
        );
        $this->assertEquals(
            new CarbonImmutable('2019-09-30T23:59:59Z'),
            $billingPeriod->endDate,
            'Could not verify end date of advance period'
        );

        // Check when subscription has been billed before
        $subscription = $this->getSubscription();
        $subscription->period_start = (int) mktime(0, 0, 0, 8, 1, 2019);
        $subscription->period_end = (int) mktime(23, 59, 59, 8, 31, 2019);
        $subscription->renewed_last = (int) mktime(0, 0, 0, 8, 1, 2019);
        $billingPeriod = $this->getBillingPeriods($subscription)->determinePeriodToAdvance();
        $this->assertEquals(
            new CarbonImmutable('2019-10-01'),
            $billingPeriod->billDate,
            'Could not verify bill date of advance period'
        );
        $this->assertEquals(
            new CarbonImmutable('2019-09-01'),
            $billingPeriod->startDate,
            'Could not verify start date of advance period'
        );
        $this->assertEquals(
            new CarbonImmutable('2019-09-30T23:59:59Z'),
            $billingPeriod->endDate,
            'Could not verify end date of advance period'
        );

        // Check when subscription is not in trial but not billed yet
        $subscription = $this->getSubscription();
        $subscription->period_start = (int) mktime(0, 0, 0, 8, 1, 2019);
        $subscription->period_end = (int) mktime(23, 59, 59, 8, 31, 2019);
        $billingPeriod = $this->getBillingPeriods($subscription)->determinePeriodToAdvance();
        $this->assertEquals(
            new CarbonImmutable('2019-09-01'),
            $billingPeriod->billDate,
            'Could not verify bill date of advance period'
        );
        $this->assertEquals(
            new CarbonImmutable('2019-08-01'),
            $billingPeriod->startDate,
            'Could not verify start date of advance period'
        );
        $this->assertEquals(
            new CarbonImmutable('2019-08-31T23:59:59Z'),
            $billingPeriod->endDate,
            'Could not verify end date of advance period'
        );
    }

    public function testDeterminePeriodToAdvanceArrears(): void
    {
        $subscription = $this->getSubscription();
        $subscription->bill_in = Subscription::BILL_IN_ARREARS;
        $billingPeriods = $this->getBillingPeriods($subscription);

        $this->assertEquals($billingPeriods->next(), $billingPeriods->determinePeriodToAdvance());
    }

    public function testBillsIn(): void
    {
        $subscription = new Subscription();
        $subscription->tenant_id = (int) self::$company->id();
        $periods = $this->getBillingPeriods($subscription);
        $this->assertEquals('', $periods->billsIn());

        $periods = $this->getBillingPeriods($subscription);
        $subscription = $periods->getSubscription();
        $subscription->renews_next = (int) mktime(23, 59, 59, 1, 1, 2017);
        CarbonImmutable::setTestNow('2017-01-01T12:12:12Z');
        $this->assertEquals('less than 1 day', $periods->billsIn());

        $subscription->renews_next = (int) mktime(0, 0, 0, 1, 3, 2017);
        CarbonImmutable::setTestNow('2017-01-02');
        $this->assertEquals('1 day', $periods->billsIn());

        $subscription->renews_next = (int) mktime(0, 0, 0, 1, 4, 2017);
        CarbonImmutable::setTestNow('2017-01-01');
        $this->assertEquals('3 days', $periods->billsIn());

        $subscription->renews_next = (int) mktime(0, 0, 0, 1, 9, 2017);
        CarbonImmutable::setTestNow('2017-01-02');
        $this->assertEquals('7 days', $periods->billsIn());

        // The time until bill should count the # of day
        // boundaries that are crossed and not the rounded down
        // number of days remaining.
        // In this test we are going to verify that a subscription
        // renewing at the start of tomorrow returns 1 day,
        // instead of returning "less than 1 day" / "now".
        $subscription->renews_next = (int) mktime(12, 12, 12, 1, 2, 2017);
        CarbonImmutable::setTestNow('2017-01-01');
        $this->assertEquals('1 day', $periods->billsIn());
    }

    public function testPercentTimeRemainingPastCycle(): void
    {
        $periods = $this->getBillingPeriods();
        /** @var CarbonImmutable $time */
        $time = self::$startOfToday->modify('+1 month');
        $this->assertEquals(0, $periods->percentTimeRemaining($time));
    }

    public function testPercentTimeRemaining(): void
    {
        $startDate = 1000000 - (86400 * 7);
        $time = $startDate + (4 * 86400);

        $plan = new Plan();
        $plan->interval = Interval::WEEK;
        $plan->interval_count = 1;
        $subscription = new Subscription();
        $subscription->tenant_id = (int) self::$company->id();
        $subscription->setPlan($plan);
        $subscription->start_date = $startDate;
        $subscription->period_end = 1000000;
        $periods = $this->getBillingPeriods($subscription);

        // there should be 3 days left in the cycle, or 3/7%
        $this->assertEquals(round(3 / 7, 4), round($periods->percentTimeRemaining(CarbonImmutable::createFromTimestamp($time)), 4));
    }

    public function testPercentTimeRemainingCalendarBilling(): void
    {
        $periods = $this->getBillingPeriods();
        $subscription = $periods->getSubscription();
        $subscription->start_date = (int) mktime(0, 0, 0, 4, 1, 2019);
        $subscription->period_start = $subscription->start_date;
        $subscription->period_end = (int) mktime(23, 59, 59, 4, 30, 2019);
        $subscription->snap_to_nth_day = 1;

        $time = new CarbonImmutable('2019-04-15');

        // there should be 16 days left in the cycle, or 16/30%
        $this->assertEquals(round(16 / 30, 4), round($periods->percentTimeRemaining($time), 4));
    }

    public function testPercentTimeRemainingCalendarBillingArrears(): void
    {
        $periods = $this->getBillingPeriods();
        $subscription = $periods->getSubscription();
        $subscription->status = SubscriptionStatus::TRIALING;
        $subscription->start_date = (int) mktime(0, 0, 0, 9, 1, 2020);
        $subscription->period_start = (int) mktime(1, 2, 3, 8, 23, 2020);
        $subscription->period_end = (int) mktime(23, 59, 59, 8, 31, 2020);
        $subscription->renews_next = (int) mktime(23, 59, 59, 9, 14, 2020);
        $subscription->snap_to_nth_day = 15;
        $subscription->bill_in = Subscription::BILL_IN_ARREARS;
        $currentTime = new CarbonImmutable('2020-09-01');

        // there should be 14 days left in the cycle, or 14/31%
        $this->assertEquals(round(14 / 31, 4), round($periods->percentTimeRemaining($currentTime), 4));
    }

    public function testEndDate(): void
    {
        $periods = $this->getBillingPeriods();
        $subscription = $periods->getSubscription();

        $subscription->cycles = 4;
        $subscription->contract_renewal_mode = Subscription::RENEWAL_MODE_AUTO;
        $subscription->bill_in = Subscription::BILL_IN_ARREARS;
        $subscription->cancel_at_period_end = true;
        $end = $this->advanceTime(CarbonImmutable::createFromTimestamp($subscription->start_date), 4)->modify('-1 second');
        $this->assertEquals($end, $periods->endDate());
    }

    public function testEndDateInfinite(): void
    {
        $periods = $this->getBillingPeriods();
        $this->assertNull($periods->endDate());
    }

    public function testEndDateInfiniteCancelAtPeriodEnd(): void
    {
        $periods = $this->getBillingPeriods();
        $subscription = $periods->getSubscription();
        $interval = $subscription->plan()->interval();

        $subscription->cancel_at_period_end = true;
        $this->assertEquals(
            CarbonImmutable::createFromTimestamp($interval->addTo(self::$startOfToday->getTimestamp()) - 1),
            $periods->endDate()
        );
    }

    public function testEndDateNoRenewContract(): void
    {
        $periods = $this->getBillingPeriods();
        $subscription = $periods->getSubscription();

        $subscription->cycles = 4;
        $subscription->contract_renewal_mode = Subscription::RENEWAL_MODE_NONE;
        $end = $this->advanceTime(CarbonImmutable::createFromTimestamp($subscription->start_date), 4)->modify('-1 second');
        $this->assertEquals($end, $periods->endDate());
    }

    public function testEndDateManualRenewContract(): void
    {
        $periods = $this->getBillingPeriods();
        $subscription = $periods->getSubscription();

        $subscription->cycles = 4;
        $subscription->contract_renewal_mode = Subscription::RENEWAL_MODE_MANUAL;
        $end = $this->advanceTime(CarbonImmutable::createFromTimestamp($subscription->start_date), 4)->modify('-1 second');
        $this->assertEquals($end, $periods->endDate());
    }

    public function testEndDateAutoRenewContract(): void
    {
        $periods = $this->getBillingPeriods();
        $subscription = $periods->getSubscription();

        $subscription->cycles = 4;
        $subscription->contract_renewal_mode = Subscription::RENEWAL_MODE_AUTO;
        $this->assertNull($periods->endDate());
    }

    public function testEndDateRenewOnceContract(): void
    {
        $periods = $this->getBillingPeriods();
        $subscription = $periods->getSubscription();

        $subscription->cycles = 4;
        $subscription->contract_renewal_mode = Subscription::RENEWAL_MODE_RENEW_ONCE;
        $this->assertNull($periods->endDate());
    }

    public function testEndDateContractCancelAtPeriodEnd(): void
    {
        $periods = $this->getBillingPeriods();
        $subscription = $periods->getSubscription();

        $subscription->cycles = 4;
        $subscription->contract_renewal_mode = Subscription::RENEWAL_MODE_AUTO;
        $subscription->cancel_at_period_end = true;
        $end = $this->advanceTime(CarbonImmutable::createFromTimestamp($subscription->start_date), 4)->modify('-1 second');
        $this->assertEquals($end, $periods->endDate());
    }

    public function testEndDateBillInAdvanceDays(): void
    {
        $periods = $this->getBillingPeriods();
        $subscription = $periods->getSubscription();

        $subscription->cycles = 4;
        $subscription->contract_renewal_mode = Subscription::RENEWAL_MODE_AUTO;
        $subscription->bill_in_advance_days = 7;
        $subscription->cancel_at_period_end = true;
        $end = $this->advanceTime(CarbonImmutable::createFromTimestamp($subscription->start_date), 4)->modify('-1 second');
        $this->assertEquals($end, $periods->endDate());
    }

    public function testCalculatePeriodEnd(): void
    {
        $subscription = $this->getSubscription();
        $subscription->snap_to_nth_day = 1;
        $periodStart = new CarbonImmutable('2020-04-03T23:59:59Z');

        $this->assertEquals(
            new CarbonImmutable('2020-04-30T23:59:59Z'),
            BillingPeriods::calculatePeriodEnd($subscription, $periodStart),
            'Could not verify end date of next period with time zone discrepancy'
        );
    }

    protected function advanceTime(CarbonImmutable $date, int $iterations = 1): CarbonImmutable
    {
        $interval = new Interval(1, 'month');
        $timestamp = (int) $date->timestamp;
        for ($i = 0; $i < $iterations; ++$i) {
            $timestamp = $interval->addTo($timestamp);
        }

        return CarbonImmutable::createFromTimestamp($timestamp);
    }
}
