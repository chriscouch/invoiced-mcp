<?php

namespace App\Notifications\Libs;

use App\Companies\Models\Member;
use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Interfaces\NotificationEmailInterface;
use App\Notifications\Models\NotificationEvent;
use App\Notifications\Models\NotificationRecipient;
use App\Notifications\NotificationEmails\AutomationTriggered;
use App\Notifications\NotificationEmails\AutoPayFailed;
use App\Notifications\NotificationEmails\AutoPaySucceeded;
use App\Notifications\NotificationEmails\DisabledMethodsOnSignUpPageCompleted;
use App\Notifications\NotificationEmails\EmailThreadAssigned;
use App\Notifications\NotificationEmails\EstimateApproved;
use App\Notifications\NotificationEmails\EstimateViewed;
use App\Notifications\NotificationEmails\InvoiceViewed;
use App\Notifications\NotificationEmails\LockboxCheckReceived;
use App\Notifications\NotificationEmails\NetworkDocumentReceived;
use App\Notifications\NotificationEmails\NetworkDocumentStatusChanged;
use App\Notifications\NotificationEmails\NetworkInvitationAccepted;
use App\Notifications\NotificationEmails\NetworkInvitationDeclined;
use App\Notifications\NotificationEmails\EmailReceived;
use App\Notifications\NotificationEmails\NullNotificationEmail;
use App\Notifications\NotificationEmails\PaymentDone;
use App\Notifications\NotificationEmails\PaymentLinkCompleted;
use App\Notifications\NotificationEmails\PaymentPlanApproved;
use App\Notifications\NotificationEmails\PromiseCreated;
use App\Notifications\NotificationEmails\SignUpPageCompleted;
use App\Notifications\NotificationEmails\SubscriptionCanceled;
use App\Notifications\NotificationEmails\SubscriptionExpired;
use App\Notifications\NotificationEmails\TaskAssigned;
use Psr\Container\ContainerInterface;
use App\Notifications\NotificationEmails\RefundReversalApplied;

class NotificationEmailFactory
{
    public function __construct(private ContainerInterface $handlerLocator)
    {
    }

    /**
     * @return NotificationEvent[]
     */
    public function getEvents(int $typeId, Member $member): array
    {
        /** @var NotificationEvent[] $events */
        $events = [];
        $offset = 0;
        while ($result = NotificationEvent::join(NotificationRecipient::class, 'id', 'notification_event_id')
            ->where('NotificationRecipients.member_id', $member->id)
            ->where('type', $typeId)
            ->where('NotificationRecipients.sent', false)
            ->start($offset)
            ->limit(100)
            ->execute()) {
            $events = array_merge($result, $events);
            $offset += 100;
        }

        return $events;
    }

    public function build(int $typeId): NotificationEmailInterface
    {
        $type = NotificationEventType::fromInteger($typeId);
        $class = match ($type) {
            NotificationEventType::AutoPayFailed => AutoPayFailed::class,
            NotificationEventType::AutoPaySucceeded => AutoPaySucceeded::class,
            NotificationEventType::AutomationTriggered => AutomationTriggered::class,
            NotificationEventType::EmailReceived => EmailReceived::class,
            NotificationEventType::EstimateApproved => EstimateApproved::class,
            NotificationEventType::EstimateViewed => EstimateViewed::class,
            NotificationEventType::InvoiceViewed => InvoiceViewed::class,
            NotificationEventType::LockboxCheckReceived => LockboxCheckReceived::class,
            NotificationEventType::NetworkDocumentReceived => NetworkDocumentReceived::class,
            NotificationEventType::NetworkDocumentStatusChange => NetworkDocumentStatusChanged::class,
            NotificationEventType::NetworkInvitationAccepted => NetworkInvitationAccepted::class,
            NotificationEventType::NetworkInvitationDeclined => NetworkInvitationDeclined::class,
            NotificationEventType::PaymentDone => PaymentDone::class,
            NotificationEventType::PaymentLinkCompleted => PaymentLinkCompleted::class,
            NotificationEventType::PaymentPlanApproved => PaymentPlanApproved::class,
            NotificationEventType::PromiseCreated => PromiseCreated::class,
            NotificationEventType::ReconciliationError => NullNotificationEmail::class,
            NotificationEventType::SignUpPageCompleted => SignUpPageCompleted::class,
            NotificationEventType::SubscriptionCanceled => SubscriptionCanceled::class,
            NotificationEventType::SubscriptionExpired => SubscriptionExpired::class,
            NotificationEventType::TaskAssigned => TaskAssigned::class,
            NotificationEventType::ThreadAssigned => EmailThreadAssigned::class,
            NotificationEventType::DisabledMethodsOnSignUpPageCompleted => DisabledMethodsOnSignUpPageCompleted::class,
            NotificationEventType::RefundReversalApplied => RefundReversalApplied::class,
        };

        return $this->handlerLocator->get($class);
    }
}
