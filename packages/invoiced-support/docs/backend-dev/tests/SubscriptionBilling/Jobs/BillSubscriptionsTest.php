<?php

namespace App\Tests\SubscriptionBilling\Jobs;

use App\AccountsReceivable\Models\Coupon;
use App\AccountsReceivable\Models\Invoice;
use App\Core\Cron\ValueObjects\Run;
use App\Core\Queue\Queue;
use App\Core\Queue\QueueServiceLevel;
use App\Core\Statsd\StatsdClient;
use App\Core\Utils\Enums\ObjectType;
use App\EntryPoint\CronJob\BillSubscriptions;
use App\EntryPoint\QueueJob\BillSubscriptionsJob;
use App\Metadata\Models\CustomField;
use App\SubscriptionBilling\Models\CouponRedemption;
use App\SubscriptionBilling\Models\Subscription;
use App\SubscriptionBilling\ValueObjects\SubscriptionStatus;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Mockery;

class BillSubscriptionsTest extends AppTestCase
{
    private static Coupon $limitedCoupon;
    private static CarbonImmutable $start;
    private static Subscription $subscriptionContractNoRenewal;
    private static Subscription $subscriptionContractManualRenewal;
    private static Subscription $subscriptionContractRenewOnce;
    private static Subscription $subscriptionContractAutoRenewal;
    private static Subscription $subscriptionLimitedCoupon;
    private static Subscription $subscriptionToBeCanceled1;
    private static Subscription $subscriptionToBeCanceled2;
    private static Subscription $subscriptionToBeCanceled3;
    private static Subscription $subscriptionToBeCanceled4;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasCoupon();
        self::hasTaxRate();
        self::hasPlan();
        self::hasItem();

        // enable manual contract renewals for testing
        self::$company->features->enable('subscription_manual_renewal');

        $customField1 = new CustomField();
        $customField1->id = 'account-rep';
        $customField1->object = ObjectType::Subscription->typeName();
        $customField1->name = 'Account Rep';
        $customField1->saveOrFail();

        self::$limitedCoupon = new Coupon();
        self::$limitedCoupon->id = 'limited';
        self::$limitedCoupon->name = 'Limited';
        self::$limitedCoupon->duration = 2;
        self::$limitedCoupon->is_percent = true;
        self::$limitedCoupon->value = 5;
        self::$limitedCoupon->saveOrFail();

        // Watch out for the 2030 bug, when Jan 1, 2030 rolls
        // around these tests will no longer work ;)
        self::$start = new CarbonImmutable('2030-01-01');

        $createSubscription = self::getService('test.create_subscription');
        self::$subscription = $createSubscription->create([
            'customer' => self::$customer,
            'plan' => self::$plan,
            'description' => '$subscription',
            'quantity' => 2,
            'start_date' => self::$start->getTimestamp(),
            'metadata' => (object) ['account-rep' => 'Jan'],
            'ship_to' => [
                'name' => 'Test',
                'address1' => '1234 main st',
                'city' => 'Austin',
                'state' => 'TX',
                'postal_code' => '78701',
                'country' => 'US',
            ],
        ]);

        self::$subscriptionContractNoRenewal = $createSubscription->create([
            'customer' => self::$customer,
            'plan' => self::$plan,
            'description' => '$subscriptionContractNoRenewal',
            'cycles' => 4,
            'start_date' => self::$start->getTimestamp(),
            'contract_renewal_mode' => Subscription::RENEWAL_MODE_NONE,
            'discounts' => ['coupon'],
            'tax' => ['tax'],
            'addons' => [[
                'catalog_item' => self::$item->id,
                'quantity' => 2,
            ]],
        ]);

        self::$subscriptionContractManualRenewal = $createSubscription->create([
            'customer' => self::$customer,
            'plan' => self::$plan,
            'description' => '$subscriptionContractManualRenewal',
            'cycles' => 4,
            'start_date' => self::$start->getTimestamp(),
            'contract_renewal_mode' => Subscription::RENEWAL_MODE_MANUAL,
        ]);

        self::$subscriptionContractAutoRenewal = $createSubscription->create([
            'customer' => self::$customer,
            'plan' => self::$plan,
            'description' => '$subscriptionContractAutoRenewal',
            'cycles' => 4,
            'start_date' => self::$start->getTimestamp(),
            'contract_renewal_mode' => Subscription::RENEWAL_MODE_AUTO,
        ]);

        self::$subscriptionContractRenewOnce = $createSubscription->create([
            'customer' => self::$customer,
            'plan' => self::$plan,
            'description' => '$subscriptionContractRenewOnce',
            'cycles' => 4,
            'start_date' => self::$start->getTimestamp(),
            'contract_renewal_mode' => Subscription::RENEWAL_MODE_RENEW_ONCE,
        ]);

        self::$subscriptionLimitedCoupon = $createSubscription->create([
            'customer' => self::$customer,
            'plan' => self::$plan,
            'description' => '$subscriptionLimitedCoupon',
            'start_date' => self::$start->getTimestamp(),
            'discounts' => [self::$limitedCoupon->id],
        ]);

        self::$subscriptionToBeCanceled1 = $createSubscription->create([
            'customer' => self::$customer,
            'plan' => self::$plan,
            'description' => '$subscriptionToBeCanceled',
            'start_date' => self::$start->getTimestamp(),
            'cancel_at_period_end' => true,
        ]);

        self::$subscriptionToBeCanceled2 = $createSubscription->create([
            'customer' => self::$customer,
            'plan' => self::$plan,
            'description' => '$subscriptionToBeCanceled2',
            'start_date' => self::$start->getTimestamp(),
            'cancel_at_period_end' => true,
        ]);

        self::$subscriptionToBeCanceled3 = $createSubscription->create([
            'customer' => self::$customer,
            'plan' => self::$plan,
            'description' => '$subscriptionToBeCanceled3',
            'start_date' => self::$start->getTimestamp(),
            'cancel_at_period_end' => true,
            'cycles' => 4,
        ]);

        self::$subscriptionToBeCanceled4 = $createSubscription->create([
            'customer' => self::$customer,
            'plan' => self::$plan,
            'description' => '$subscriptionToBeCanceled4',
            'start_date' => self::$start->getTimestamp(),
            'cycles' => 1,
            'contract_renewal_mode' => Subscription::RENEWAL_MODE_AUTO,
        ]);

        $subscriptionPaused = $createSubscription->create([
            'customer' => self::$customer,
            'plan' => self::$plan,
            'description' => '$subscriptionPaused',
            'start_date' => self::$start->getTimestamp(),
            'paused' => true,
            'contract_renewal_mode' => Subscription::RENEWAL_MODE_AUTO,
        ]);

        // renew subscriptions to reach desired states for our test bench
        $operation = self::getService('test.bill_subscription');
        CarbonImmutable::setTestNow(CarbonImmutable::createFromTimestamp((int) self::$subscription->renews_next));
        $operation->bill(self::$subscription);
        CarbonImmutable::setTestNow(CarbonImmutable::createFromTimestamp((int) self::$subscription->renews_next));
        $operation->bill(self::$subscription);
        CarbonImmutable::setTestNow(CarbonImmutable::createFromTimestamp((int) self::$subscriptionToBeCanceled1->renews_next));
        $operation->bill(self::$subscriptionToBeCanceled1, true);
        for ($i = 0; $i < 4; ++$i) {
            CarbonImmutable::setTestNow(CarbonImmutable::createFromTimestamp((int) self::$subscriptionToBeCanceled3->renews_next));
            $operation->bill(self::$subscriptionToBeCanceled3, true);
        }
        CarbonImmutable::setTestNow(CarbonImmutable::createFromTimestamp((int) self::$subscriptionToBeCanceled4->renews_next));
        $operation->bill(self::$subscriptionToBeCanceled4, true);
        // cancelling at the end of the last term needs to be billed out until it is canceled
        self::$subscriptionToBeCanceled4->cancel_at_period_end = true;
        self::$subscriptionToBeCanceled4->saveOrFail();
        CarbonImmutable::setTestNow(CarbonImmutable::createFromTimestamp((int) self::$subscriptionToBeCanceled4->renews_next));
        $operation->bill(self::$subscriptionToBeCanceled4, true);
        for ($i = 0; $i < 4; ++$i) {
            CarbonImmutable::setTestNow(CarbonImmutable::createFromTimestamp((int) self::$subscriptionContractManualRenewal->renews_next));
            $operation->bill(self::$subscriptionContractManualRenewal, true);
        }
        for ($i = 0; $i < 4; ++$i) {
            CarbonImmutable::setTestNow(CarbonImmutable::createFromTimestamp((int) self::$subscriptionContractRenewOnce->renews_next));
            $operation->bill(self::$subscriptionContractRenewOnce, true);
        }
        for ($i = 0; $i < 4; ++$i) {
            CarbonImmutable::setTestNow(CarbonImmutable::createFromTimestamp((int) self::$subscriptionContractAutoRenewal->renews_next));
            $operation->bill(self::$subscriptionContractAutoRenewal, true);
        }
    }

    private function getQueueJob(): BillSubscriptionsJob
    {
        $job = new BillSubscriptionsJob(self::getService('test.lock_factory'), self::getService('test.bill_subscription'));
        $job->setLogger(self::$logger);

        return $job;
    }

    public function testExecute(): void
    {
        $queue = Mockery::mock(Queue::class);
        $queue->shouldReceive('enqueue')
            ->withArgs([
                BillSubscriptionsJob::class,
                ['tenant_id' => self::$company->id],
                QueueServiceLevel::Batch,
            ])
            ->once();
        $job = new BillSubscriptions(self::getService('test.database'), $queue);
        $job->setStatsd(new StatsdClient());
        $job->execute(new Run());
    }

    public function testGetSubscriptions(): void
    {
        CarbonImmutable::setTestNow(self::$start->modify('+5 months'));
        $subscriptions = $this->getQueueJob()->getSubscriptions();

        $this->assertCount(4, $subscriptions);

        $found = [];
        foreach ($subscriptions as $subscription) {
            $found[] = $subscription->id();
        }

        $this->assertTrue(in_array(self::$subscription->id(), $found), 'First subscription not found');
        $this->assertTrue(in_array(self::$subscriptionContractNoRenewal->id(), $found), 'Fixed duration subscription not found');
        $this->assertTrue(in_array(self::$subscriptionLimitedCoupon->id(), $found), 'Limited coupon subscription not found');
        $this->assertTrue(in_array(self::$subscriptionToBeCanceled2->id(), $found), 'Subscription to be canceled 2 not found');
    }

    public function testPerform(): void
    {
        // the plan is bi-monthly. also, we have already billed
        // once. start with N=2 and bill all subscriptions
        // every 2 months for a total of 5 cycles. running
        // for 5 cycles in order to test the fixed duration
        // stops after 4 cycles.
        $job = $this->getQueueJob();
        for ($n = 2; $n < 12; $n += 2) {
            CarbonImmutable::setTestNow(self::$start->modify("+ $n months"));
            $job->args = ['tenant_id' => self::$company->id()];
            $job->perform();
        }

        // verify 5 more invoices were created for the first subscription (in addition to first)
        $this->assertGreaterThan(self::$start->getTimestamp(), self::$subscription->refresh()->renews_next);
        $n = Invoice::where('subscription_id', self::$subscription)->count();
        $this->assertEquals(6, $n);
        $this->assertEquals(6, self::$subscription->num_invoices);

        // verify the fixed duration subscription finished
        // by creating 4 invoices
        $this->assertNull(self::$subscriptionContractNoRenewal->refresh()->renews_next);
        $this->assertTrue(self::$subscriptionContractNoRenewal->finished);
        $this->assertNull(self::$subscriptionContractNoRenewal->contract_period_start);
        $this->assertNull(self::$subscriptionContractNoRenewal->contract_period_end);
        $this->assertEquals(SubscriptionStatus::FINISHED, self::$subscriptionContractNoRenewal->status);
        $n = Invoice::where('subscription_id', self::$subscriptionContractNoRenewal)->count();
        $this->assertEquals(4, $n);
        $this->assertEquals(4, self::$subscriptionContractNoRenewal->num_invoices);
        // NOTE cannot check the status = finished since the
        // subscription starts in the future.

        // verify the limited duration coupon finished
        // and was only billed twice
        $invoices = Invoice::where('subscription_id', self::$subscriptionLimitedCoupon)->all();
        $this->assertCount(6, $invoices);
        $this->assertEquals(95, $invoices[0]->total);
        $this->assertEquals(95, $invoices[1]->total);
        for ($i = 2; $i < count($invoices); ++$i) {
            $this->assertEquals(100, $invoices[$i]->total);
        }
        $this->assertEquals(6, self::$subscriptionLimitedCoupon->refresh()->num_invoices);

        // the coupon redemption should be used twice and not active
        /** @var CouponRedemption $redemption */
        $redemption = CouponRedemption::where('parent_type', ObjectType::Subscription->typeName())
            ->where('parent_id', self::$subscriptionLimitedCoupon)
            ->one();
        $this->assertFalse($redemption->active);
        $this->assertEquals(2, $redemption->num_uses);

        // this subscription should be canceled
        $this->assertTrue(self::$subscriptionToBeCanceled2->refresh()->canceled);
        $this->assertEquals(SubscriptionStatus::CANCELED, self::$subscriptionToBeCanceled2->status);
        $this->assertEquals(0, self::$subscriptionToBeCanceled2->num_invoices);

        // there should be no subscriptions that need to be billed
        CarbonImmutable::setTestNow(self::$start);
        $this->assertCount(0, $job->getSubscriptions());
    }
}
