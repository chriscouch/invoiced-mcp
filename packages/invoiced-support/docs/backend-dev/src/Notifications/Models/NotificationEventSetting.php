<?php

namespace App\Notifications\Models;

use App\Companies\Models\Member;
use App\ActivityLog\Enums\EventType;
use App\Notifications\Enums\NotificationEventType;
use App\Core\Orm\Property;

/**
 * @property Member $member
 * @property int    $member_id
 */
class NotificationEventSetting extends AbstractNotificationEventSetting
{
    const CONVERSION_LIST = [
        [EventType::ChargeFailed->value, NotificationEventType::AutoPayFailed],
        [EventType::EstimateApproved->value, NotificationEventType::EstimateApproved],
        [EventType::EstimateCommented->value, NotificationEventType::EmailReceived],
        [EventType::EstimateViewed->value, NotificationEventType::EstimateViewed],
        [EventType::InvoiceCommented->value, NotificationEventType::EmailReceived],
        [EventType::InvoicePaymentExpected->value, NotificationEventType::PromiseCreated],
        [EventType::InvoiceViewed->value, NotificationEventType::InvoiceViewed],
        [EventType::PaymentCreated->value, NotificationEventType::PaymentDone],
        [EventType::SubscriptionCanceled->value, NotificationEventType::SubscriptionCanceled],
        [EventType::SubscriptionCanceled->value, NotificationEventType::SubscriptionExpired],
    ];

    protected static function getProperties(): array
    {
        return [
            'member' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                belongs_to: Member::class,
            ),
        ];
    }

    public function toArray(): array
    {
        $result = parent::toArray();
        $result['member_id'] = $this->member_id;

        return $result;
    }
}
