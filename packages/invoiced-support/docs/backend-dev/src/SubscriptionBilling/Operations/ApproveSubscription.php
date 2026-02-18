<?php

namespace App\SubscriptionBilling\Operations;

use App\SubscriptionBilling\Models\Subscription;
use App\SubscriptionBilling\Models\SubscriptionApproval;

class ApproveSubscription
{
    /**
     * Approves this subscription.
     */
    public function approve(Subscription $subscription, string $ip, string $userAgent): SubscriptionApproval
    {
        $approval = new SubscriptionApproval();
        $approval->subscription_id = (int) $subscription->id();
        $approval->ip = $ip;
        $approval->user_agent = $userAgent;
        $approval->save();

        $subscription->approval_id = (int) $approval->id();
        $subscription->save();

        return $approval;
    }
}
