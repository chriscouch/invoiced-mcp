<?php

namespace App\SubscriptionBilling\Search;

use App\Core\Search\Interfaces\SearchDocumentInterface;
use App\SubscriptionBilling\Models\Subscription;

class SubscriptionSearchDocument implements SearchDocumentInterface
{
    public function __construct(private Subscription $subscription)
    {
    }

    public function toSearchDocument(): array
    {
        $customer = $this->subscription->customer();
        $plan = $this->subscription->plan();

        return [
            'plan' => [
                'id' => $plan->id,
                'name' => $plan->name,
                'currency' => $plan->currency,
                'interval' => $plan->interval,
                'interval_count' => $plan->interval_count,
            ],
            'recurring_total' => $this->subscription->recurring_total,
            'status' => $this->subscription->status,
            'metadata' => (array) $this->subscription->metadata,
            '_customer' => $customer->id(),
            'customer' => [
                'name' => $customer->name,
            ],
        ];
    }
}
