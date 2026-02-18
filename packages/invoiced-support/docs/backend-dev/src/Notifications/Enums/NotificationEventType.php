<?php

namespace App\Notifications\Enums;

use InvalidArgumentException;

enum NotificationEventType: string
{
    case EmailReceived = 'email.received'; // New email is received
    case ThreadAssigned = 'thread.assigned'; // Email thread is assigned to me
    case TaskAssigned = 'task.assigned'; // Task is assigned to me
    case InvoiceViewed = 'invoice.viewed'; // Customer views an invoice in the customer portal
    case EstimateViewed = 'estimate.viewed'; // Customer views an estimate in the customer portal
    case PaymentDone = 'payment.done'; // Customer makes a payment in the customer portal
    case PromiseCreated = 'promise.created'; // Customer creates a promise-to-pay in the customer portal
    case EstimateApproved = 'estimate.approved'; // Customer approves an estimate
    case PaymentPlanApproved = 'payment_plan.approved'; // Customer approves a payment plan
    case AutoPayFailed = 'autopay.failed'; // AutoPay payment fails
    case AutoPaySucceeded = 'autopay.succeeded'; // AutoPay payment succeeds
    case SubscriptionCanceled = 'subscription.canceled'; // Subscription is canceled in the customer portal
    case SubscriptionExpired = 'subscription.expired'; // Subscription is canceled due to nonpayment
    case SignUpPageCompleted = 'sign_up_page.completed'; // Customer completed a sign up page
    /* @deprecated */
    case ReconciliationError = 'reconciliation.error'; // Reconciliation error occurs in the accounting sync
    case LockboxCheckReceived = 'lock_box.received'; // lockbox check received
    case NetworkInvitationDeclined = 'network_invitation.declined'; // Network invitation is declined
    case NetworkInvitationAccepted = 'network_invitation.accepted'; // Network invitation is accepted
    case NetworkDocumentReceived = 'network_document.received'; // Document is received through network
    case NetworkDocumentStatusChange = 'network_document_status.changed'; // Document status is changed
    case AutomationTriggered = 'automation.triggered'; // automation has been triggered
    case PaymentLinkCompleted = 'payment_link.completed';
    case DisabledMethodsOnSignUpPageCompleted = 'sign_up_page.payment_methods_disabled';
    case RefundReversalApplied = 'refund.reversal_applied'; // Refund reversal applied

    public function toInteger(): int
    {
        return match ($this) {
            self::EmailReceived => 1,
            self::ThreadAssigned => 2,
            self::TaskAssigned => 3,
            self::InvoiceViewed => 4,
            self::EstimateViewed => 5,
            self::PaymentDone => 6,
            self::PromiseCreated => 7,
            self::EstimateApproved => 8,
            self::PaymentPlanApproved => 9,
            self::AutoPayFailed => 10,
            self::SubscriptionCanceled => 11,
            self::ReconciliationError => 12,
            self::SubscriptionExpired => 13,
            self::AutoPaySucceeded => 14,
            self::SignUpPageCompleted => 15,
            self::LockboxCheckReceived => 16,
            self::NetworkInvitationDeclined => 17,
            self::NetworkInvitationAccepted => 18,
            self::NetworkDocumentReceived => 19,
            self::NetworkDocumentStatusChange => 20,
            self::AutomationTriggered => 21,
            self::PaymentLinkCompleted => 22,
            self::DisabledMethodsOnSignUpPageCompleted => 23,
            self::RefundReversalApplied => 24,
        };
    }

    public static function fromInteger(int $id): self
    {
        foreach (self::cases() as $case) {
            if ($case->toInteger() == $id) {
                return $case;
            }
        }

        throw new InvalidArgumentException('Integer not mapped: '.$id);
    }
}
