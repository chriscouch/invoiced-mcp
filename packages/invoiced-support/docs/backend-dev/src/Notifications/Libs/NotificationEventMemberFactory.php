<?php

namespace App\Notifications\Libs;

use App\AccountsReceivable\Libs\CustomerPermissionHelper;
use App\AccountsReceivable\Models\Customer;
use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Enums\NotificationFrequency;
use App\Notifications\Models\NotificationEventSetting;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use InvalidArgumentException;

class NotificationEventMemberFactory
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * Generates a list of member IDs that should receive
     * a notification for the given notification type and context.
     *
     * @return int[] - array of user ids
     */
    public function getMemberIds(NotificationEventType $type, null|int|array $contextId): array
    {
        // Types of notification contexts:
        // 1. Customer w/ a given customer ID
        // 2. User w/ a given member ID
        // 3. None - default behavior
        if ($this->isCustomerNotification($type)) {
            if (!is_numeric($contextId)) {
                throw new InvalidArgumentException('Context ID should never be null');
            }

            return $this->getMemberIdsForCustomerNotification($type, $contextId);
        }

        if ($this->canBeCustomerNotification($type) && is_numeric($contextId)) {
            return $this->getMemberIdsForCustomerNotification($type, $contextId);
        }

        if ($this->isUserNotification($type)) {
            if (null === $contextId) {
                throw new InvalidArgumentException('Context ID should never be null');
            }

            if (!is_array($contextId)) {
                $contextId = [$contextId];
            }

            return array_map(fn ($item) => (int) $item, array_filter($contextId, 'is_numeric'));
        }

        return $this->getMemberIdsForSimpleNotification($type);
    }

    /**
     * Checks if this notification type has a context that points to a specific customer.
     */
    private function isCustomerNotification(NotificationEventType $type): bool
    {
        return in_array($type, [
            NotificationEventType::InvoiceViewed,
            NotificationEventType::EstimateViewed,
            NotificationEventType::PaymentDone,
            NotificationEventType::PromiseCreated,
            NotificationEventType::EstimateApproved,
            NotificationEventType::PaymentPlanApproved,
            NotificationEventType::AutoPayFailed,
            NotificationEventType::SubscriptionCanceled,
            NotificationEventType::SubscriptionExpired,
            NotificationEventType::AutoPaySucceeded,
            NotificationEventType::SignUpPageCompleted,
            NotificationEventType::PaymentLinkCompleted,
            NotificationEventType::DisabledMethodsOnSignUpPageCompleted,
            NotificationEventType::RefundReversalApplied,
        ]);
    }

    private function canBeCustomerNotification(NotificationEventType $type): bool
    {
        return in_array($type, [
            NotificationEventType::EmailReceived,
            NotificationEventType::NetworkDocumentStatusChange,
        ]);
    }

    /**
     * Gets a list of member IDs to be notified when the notification
     * context references a specific customer. This method will take
     * into account each member's customer permission settings
     * and subscriptions to notifications about the customer.
     */
    private function getMemberIdsForCustomerNotification(NotificationEventType $type, int $customerId): array
    {
        $customer = Customer::find($customerId);
        if (!$customer) {
            return [];
        }

        $settings = $this->getNotificationSettings($type);
        if (!$settings) {
            return [];
        }
        $ids = array_map(fn ($setting) => $setting->member_id, $settings);

        $qb = $this->connection->createQueryBuilder();
        $subscriptions = $qb->select('member_id, subscribe')
            ->from('NotificationSubscriptions')
            ->where('customer_id = :customer')
            ->andWhere($qb->expr()->in('member_id', ':ids'))
            ->setParameter('customer', $customer->id)
            ->setParameter('ids', $ids, ArrayParameterType::INTEGER)
            ->executeQuery();

        $subscribers = [];
        while (($row = $subscriptions->fetchAssociative()) !== false) {
            $subscribers[$row['member_id']] = $row['subscribe'];
        }

        $result = [];
        foreach ($settings as $setting) {
            $candidate = $setting->member;
            $memberId = $candidate->id;

            if (!CustomerPermissionHelper::canSeeCustomer($customer, $candidate)) {
                continue;
            }

            if (isset($subscribers[$memberId])) {
                // subscribed
                if ($subscribers[$memberId]) {
                    $result[] = $memberId;
                }
                // unsubscribed
                continue;
            }
            if ($candidate->subscribe_all) {
                $result[] = $memberId;
            }
        }

        return $result;
    }

    /**
     * Checks if this notification type has a context that points to a specific user.
     */
    private function isUserNotification(NotificationEventType $type): bool
    {
        return in_array($type, [
            NotificationEventType::ThreadAssigned,
            NotificationEventType::TaskAssigned,
            NotificationEventType::NetworkInvitationDeclined,
            NotificationEventType::NetworkInvitationAccepted,
            NotificationEventType::AutomationTriggered,
        ]);
    }

    /**
     * Gets a list of member IDs to be notified when the notification
     * context does not reference a customer or a specific user. This
     * is the default behavior for notifications.
     */
    private function getMemberIdsForSimpleNotification(NotificationEventType $type): array
    {
        $result = [];
        foreach ($this->getNotificationSettings($type) as $setting) {
            if ($setting->member->notifications) {
                $result[] = $setting->member_id;
            }
        }

        return $result;
    }

    /**
     * @return NotificationEventSetting[]
     */
    private function getNotificationSettings(NotificationEventType $type): array
    {
        /** @var NotificationEventSetting[] $settings */
        $settings = NotificationEventSetting::where('notification_type', $type->toInteger())
            ->where('frequency', NotificationFrequency::Never->toInteger(), '!=')
            ->with('member')
            ->all()
            ->toArray();
        $result = [];
        foreach ($settings as $setting) {
            if ($setting->member->notifications) {
                $result[] = $setting;
            }
        }

        return $result;
    }
}
