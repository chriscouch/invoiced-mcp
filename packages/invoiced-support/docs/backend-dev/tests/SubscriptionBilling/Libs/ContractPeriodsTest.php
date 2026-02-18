<?php

namespace App\Tests\SubscriptionBilling\Libs;

use App\SubscriptionBilling\Models\Plan;
use App\Core\Utils\ValueObjects\Interval;
use App\SubscriptionBilling\Libs\ContractPeriods;
use App\SubscriptionBilling\Models\Subscription;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;

class ContractPeriodsTest extends AppTestCase
{
    private static int $startOfToday;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        date_default_timezone_set('UTC');
        self::hasCompany();

        $start = time();
        self::$startOfToday = (int) mktime(
            0,
            0,
            0,
            (int) date('n', $start),
            (int) date('j', $start),
            (int) date('Y', $start)
        );
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

        $subscription->start_date = self::$startOfToday;
        $subscription->period_start = self::$startOfToday;
        $subscription->period_end = $interval->addTo($subscription->period_start) - 1;
        $subscription->renews_next = self::$startOfToday;
        $subscription->renewed_last = null;

        return $subscription;
    }

    private function getContractPeriods(?Subscription $subscription = null): ContractPeriods
    {
        return new ContractPeriods($subscription ?? $this->getSubscription());
    }

    public function testEndDateNoContract(): void
    {
        $periods = $this->getContractPeriods();
        $this->assertNull($periods->endDate());
    }

    public function testEndDateNewContract(): void
    {
        $periods = $this->getContractPeriods();
        $subscription = $periods->getSubscription();

        $subscription->cycles = 4;
        $end = $this->advanceTime(CarbonImmutable::createFromTimestamp($subscription->period_end + 1), 3)->modify('-1 second');
        $this->assertEquals($end, $periods->endDate());
    }

    public function testEndDateExistingContract(): void
    {
        $periods = $this->getContractPeriods();
        $subscription = $periods->getSubscription();

        $subscription->cycles = 4;
        $subscription->contract_period_start = 200;

        $end = $this->advanceTime(new CarbonImmutable('@200'), 4)->modify('-1 second');
        $this->assertEquals($end, $periods->endDate());
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
