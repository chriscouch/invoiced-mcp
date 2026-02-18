<?php

namespace App\Tests\SubscriptionBilling\Operations;

use App\Core\Utils\Enums\ObjectType;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\EventSpool;
use App\ActivityLog\Models\Event;
use App\Notifications\Libs\NotificationSpool;
use App\Sending\Email\Libs\EmailSpool;
use App\SubscriptionBilling\Exception\OperationException;
use App\SubscriptionBilling\Models\Subscription;
use App\SubscriptionBilling\Operations\CancelSubscription;
use App\SubscriptionBilling\ValueObjects\SubscriptionStatus;
use App\Tests\AppTestCase;
use Mockery;

class CancelSubscriptionTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::hasPlan();
    }

    private function getOperation(): CancelSubscription
    {
        return self::getService('test.cancel_subscription');
    }

    public function testCancel(): void
    {
        $subscription = self::getService('test.create_subscription')->create([
            'customer' => self::$customer,
            'plan' => self::$plan,
        ]);

        // cancel it
        EventSpool::enable();
        $cancelSubscription = $this->getOperation();
        $cancelSubscription->cancel($subscription, 'test');

        // verify cancellation
        $this->assertTrue($subscription->canceled, 'Should be canceled');
        $this->assertEquals('test', $subscription->canceled_reason);
        $this->assertEquals(SubscriptionStatus::CANCELED, $subscription->status, 'Should have a canceled status');
        $this->assertNull($subscription->period_start, 'Current billing period start should be cleared');
        $this->assertNull($subscription->period_end, 'Current billing period end should be cleared');
        $this->assertNull($subscription->renews_next, 'Should not have a renewal date');
        $this->assertLessThan(3, abs($subscription->canceled_at - time()), '`canceled_at` was not set to the current timestamp');

        self::getService('test.event_spool')->flush(); // write out events

        // should create a `subscription.deleted` event
        $event = Event::where('type_id', EventType::SubscriptionCanceled->toInteger())
            ->where('object_type_id', ObjectType::Subscription->value)
            ->where('object_id', $subscription)
            ->oneOrNull();
        $this->assertInstanceOf(Event::class, $event, 'Should create a subscription.deleted event');

        // try to look it up
        $subscription2 = Subscription::find($subscription->id());
        $this->assertInstanceOf(Subscription::class, $subscription2, 'Canceled subscription should still exist');

        // should not be able to cancel it again
        $spool = Mockery::mock(NotificationSpool::class);
        $spool->shouldNotReceive('spool');
        $cancelSubscription = new CancelSubscription(self::getService('test.event_spool'), $spool, Mockery::mock(EmailSpool::class));

        try {
            $cancelSubscription->cancel($subscription);
            $this->assertFalse(true, 'Should not be able to cancel an already canceled subscription.');
        } catch (OperationException $e) {
            // do nothing
        }

        // should not be able to reactivate the subscription
        try {
            self::getService('test.edit_subscription')->modify($subscription, ['canceled' => false]);
            $this->assertFalse(true, 'Should not be able to reactivate an already canceled subscription.');
        } catch (OperationException $e) {
            // do nothing
            $this->assertEquals('Canceled subscriptions cannot be reactivated. Please create a new subscription instead.', $e->getMessage());
        }
    }
}
