<?php

namespace App\ActivityLog\Libs\Messages;

use App\ActivityLog\ValueObjects\AttributedObject;
use App\ActivityLog\ValueObjects\AttributedString;
use App\SubscriptionBilling\ValueObjects\SubscriptionStatus;

class SubscriptionMessage extends BaseMessage
{
    private static array $statuses = [
        SubscriptionStatus::TRIALING => 'Trialing',
        SubscriptionStatus::PAUSED => 'Paused',
        SubscriptionStatus::PAST_DUE => 'Past Due',
        SubscriptionStatus::ACTIVE => 'Active',
        SubscriptionStatus::FINISHED => 'Finished',
        SubscriptionStatus::PENDING_RENEWAL => 'Pending Renewal',
        SubscriptionStatus::CANCELED => 'Canceled',
    ];

    protected function subscriptionCreated(): array
    {
        return [
            $this->customer('customerName'),
            new AttributedString(' subscribed to '),
            $this->plan(),
        ];
    }

    protected function subscriptionUpdated(): array
    {
        // switched plan
        if (isset($this->previous['plan']) && isset($this->object['plan'])) {
            $old = $this->previous['plan'];
            $new = $this->object['plan'];
            if (is_array($new)) {
                $new = $new['id'];
            }
            $updateStr = " switched plans from \"$old\" to \"$new\"";

            return [
                $this->customer('customerName'),
                new AttributedString($updateStr),
            ];
        }

        $updateStr = ' was updated';

        // changed status
        if (isset($this->previous['status']) && isset($this->object['status'])) {
            $old = array_value(self::$statuses, $this->previous['status']);
            $new = array_value(self::$statuses, $this->object['status']);
            $updateStr = " went from \"$old\" to \"$new\"";

            // renewed
        } elseif (array_key_exists('renewed_last', $this->previous)) {
            $updateStr = ' was renewed';

        } elseif (isset($this->previous['cancel_at_period_end']) && !$this->previous['cancel_at_period_end']) {
            if (!empty($this->object['cycles']) && $this->object['cycles'] > 0) {
                // canceled at end of contract term
                $updateStr = ' will be canceled at end of contract term';
            } else {
                // canceled at end of billing period
                $updateStr = ' will be canceled at end of billing period';
            }
        }


        return [
            new AttributedString('Subscription to '),
            $this->plan(),
            new AttributedString(' for '),
            $this->customer('customerName'),
            new AttributedString($updateStr),
        ];
    }

    protected function subscriptionDeleted(): array
    {
        return [
            $this->customer('customerName'),
            new AttributedString(' canceled their subscription to '),
            $this->plan(),
        ];
    }

    /**
     * Builds an attributed value for the plan associated
     * with this message.
     */
    private function plan(): AttributedObject
    {
        $name = '';

        // try to get the name from the plan object
        if ($this->plan) {
            $name = $this->plan->name;
        }

        // next try the value embedded in the message object
        if (!$name) {
            $name = array_value($this->object, 'plan.name');
        }

        // if all else fails, then use the generic deleted name
        if (empty(trim($name))) {
            $name = '[deleted plan]';
        }

        return new AttributedObject('plan', $name, array_value($this->associations, 'plan'));
    }
}
