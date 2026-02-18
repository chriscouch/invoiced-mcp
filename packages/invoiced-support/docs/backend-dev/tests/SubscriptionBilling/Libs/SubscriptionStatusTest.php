<?php

namespace App\Tests\SubscriptionBilling\Libs;

use App\SubscriptionBilling\ValueObjects\SubscriptionStatus;
use App\SubscriptionBilling\Models\Subscription;
use App\Tests\AppTestCase;

class SubscriptionStatusTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testFinished(): void
    {
        $subscription = new Subscription();
        $subscription->start_date = time();
        $subscription->period_start = time();
        $subscription->canceled = false;
        $subscription->finished = true;
        $status = new SubscriptionStatus($subscription);

        $this->assertEquals(SubscriptionStatus::FINISHED, $status->get());

        $subscription->canceled = false;
        $subscription->finished = true;
        $subscription->start_date = strtotime('+1 month');
        $status = new SubscriptionStatus($subscription);

        $this->assertEquals(SubscriptionStatus::FINISHED, $status->get());
    }

    public function testPendingRenewal(): void
    {
        $subscription = new Subscription();
        $subscription->start_date = time();
        $subscription->pending_renewal = true;
        $status = new SubscriptionStatus($subscription);

        $this->assertEquals(SubscriptionStatus::PENDING_RENEWAL, $status->get());
    }

    public function testNotStarted(): void
    {
        $subscription = new Subscription();
        $subscription->tenant_id = (int) self::$company->id();
        $subscription->start_date = strtotime('+1 week');
        $subscription->period_start = time();
        $subscription->canceled = false;
        $status = new SubscriptionStatus($subscription);

        $this->assertEquals(SubscriptionStatus::TRIALING, $status->get());
    }

    public function testPaused(): void
    {
        $subscription = new Subscription();
        $subscription->start_date = time();
        $subscription->period_start = time();
        $subscription->canceled = false;
        $subscription->finished = false;
        $subscription->paused = true;
        $status = new SubscriptionStatus($subscription);

        $this->assertEquals(SubscriptionStatus::PAUSED, $status->get());
    }
}
