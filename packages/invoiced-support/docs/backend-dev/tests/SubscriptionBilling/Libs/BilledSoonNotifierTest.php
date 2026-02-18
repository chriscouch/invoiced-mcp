<?php

namespace App\Tests\SubscriptionBilling\Libs;

use App\EntryPoint\CronJob\SendBilledSoonNotices;
use App\SubscriptionBilling\Libs\BilledSoonNotifier;
use App\SubscriptionBilling\Models\Subscription;
use App\Tests\AppTestCase;
use App\Core\Orm\Iterator;

class BilledSoonNotifierTest extends AppTestCase
{
    private static Subscription $subscriptionDefault;

    public static function setUpBeforeClass(): void
    {
        self::hasCompany();
        self::hasCustomer();
        self::hasPlan();
        self::hasSubscription();

        // create a subscription that renews in 2 days
        self::$subscriptionDefault = self::$subscription;
        self::$subscriptionDefault->renews_next = strtotime('+2 days');
        self::$subscriptionDefault->saveOrFail();
    }

    public function testConstructor(): void
    {
        $notifier = new BilledSoonNotifier(self::$company, 5);
        $this->assertEquals(self::$company, $notifier->getCompany());
        $this->assertEquals(5, $notifier->getDays());
        $this->assertTimestampsEqual((int) floor(strtotime('+5 days') / 86400) * 86400, $notifier->getStart()->getTimestamp());
        $this->assertTimestampsEqual((int) floor(strtotime('+5 days') / 86400) * 86400 + 86399, $notifier->getEnd()->getTimestamp());
    }

    public function testGetSubscriptions(): void
    {
        // create paused subscription
        self::hasSubscription();
        $subscription = self::$subscription;
        $subscription->paused = true;
        $subscription->period_end = strtotime('+2 days');
        $subscription->saveOrFail();

        // test results over a range of days
        for ($n = 1; $n < 10; ++$n) {
            $notifier = new BilledSoonNotifier(self::$company, $n);

            $subscriptions = $notifier->getSubscriptions();
            $this->assertInstanceOf(Iterator::class, $subscriptions);

            $ids = [];
            foreach ($subscriptions as $subscription) {
                $this->assertEquals($subscription->tenant_id, self::$company->id());

                $ids[] = $subscription->id();
            }

            if (2 == $n) {
                // because one subscription is paused
                $this->assertCount(1, $ids);
                $this->assertTrue(
                    in_array(self::$subscriptionDefault->id(), $ids),
                    "Could not find subscription in renews within $n days list when it should have been present."
                );
            } else {
                $this->assertTrue(
                    !in_array(self::$subscriptionDefault->id(), $ids),
                    "Found subscription in renews within $n days list when it should not have been present."
                );
            }
        }
    }

    public function testSend(): void
    {
        self::getService('test.email_spool')->clear();
        $job = $this->getJob();

        // send out notifications
        $this->assertEquals(1, $job->send(self::$company, 2));

        // verify
        $this->assertEquals(1, self::getService('test.email_spool')->size());
        self::getService('test.email_spool')->flush();

        // send notifications again
        // nothing should happen
        $this->assertEquals(0, $job->send(self::$company, 2));

        // verify
        $this->assertEquals(0, self::getService('test.email_spool')->size());
    }

    private function getJob(): SendBilledSoonNotices
    {
        return self::getService('test.send_billed_soon_notices');
    }
}
