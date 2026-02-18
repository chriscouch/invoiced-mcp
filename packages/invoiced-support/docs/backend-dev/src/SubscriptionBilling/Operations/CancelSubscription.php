<?php

namespace App\SubscriptionBilling\Operations;

use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\EventSpool;
use App\ActivityLog\Models\Event;
use App\ActivityLog\ValueObjects\PendingDeleteEvent;
use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Libs\NotificationSpool;
use App\Sending\Email\Libs\EmailSpool;
use App\Sending\Email\Libs\EmailTriggers;
use App\Sending\Email\Models\EmailTemplate;
use App\SubscriptionBilling\Exception\OperationException;
use App\SubscriptionBilling\Models\Subscription;
use App\SubscriptionBilling\Trait\ModifySubscriptionTrait;
use Carbon\CarbonImmutable;

class CancelSubscription
{
    use ModifySubscriptionTrait;

    public function __construct(
        private EventSpool $eventSpool,
        private NotificationSpool $notificationSpool,
        private EmailSpool $emailSpool,
    ) {
    }

    /**
     * Cancels the subscription.
     *
     * @throws OperationException
     */
    public function cancel(Subscription $subscription, ?string $canceledReason = null, ?NotificationEventType $event = null): void
    {
        if ($subscription->canceled) {
            throw new OperationException('This subscription has already been canceled.');
        }

        $subscription->canceled = true;
        if (!$subscription->canceled_at) {
            $subscription->canceled_at = CarbonImmutable::now()->getTimestamp();
        }
        if ($canceledReason) {
            $subscription->canceled_reason = $canceledReason;
        }

        $subscription->clearCurrentBillingCycle();
        $this->setStatus($subscription);

        // attempt to cancel the subscription
        EventSpool::disablePush();
        if (!$subscription->save()) {
            EventSpool::enablePop();

            throw new OperationException('Could not cancel subscription: '.$subscription->getErrors());
        }

        // create a subscription.deleted event
        $metadata = $subscription->getEventObject();
        $associations = $subscription->getEventAssociations();

        EventSpool::enablePop();
        $pendingEvent = new PendingDeleteEvent($subscription, EventType::SubscriptionCanceled, $metadata, $associations);
        $this->eventSpool->enqueue($pendingEvent);

        if ($event) {
            $this->notificationSpool->spool($event, $subscription->tenant_id, $subscription->id, $subscription->customer);
        }

        // send a cancellation email (if turned on)
        if (EmailTriggers::make($subscription->tenant())->isEnabled('subscription_canceled')) {
            $emailTemplate = EmailTemplate::make($subscription->tenant_id, EmailTemplate::SUBSCRIPTION_CANCELED);
            // If the cancel notice email fails to spool then we don't
            // pass along the error because we want the operation
            // to succeed.
            $this->emailSpool->spoolDocument($subscription, $emailTemplate, [], false);
        }
    }
}
