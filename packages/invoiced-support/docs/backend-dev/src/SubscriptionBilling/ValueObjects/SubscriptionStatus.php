<?php

namespace App\SubscriptionBilling\ValueObjects;

use App\AccountsReceivable\Enums\InvoiceStatus;
use App\AccountsReceivable\Models\Invoice;
use App\SubscriptionBilling\Models\Subscription;
use Carbon\CarbonImmutable;

/**
 * Manages the different subscription states.
 */
class SubscriptionStatus implements \Stringable
{
    const TRIALING = 'not_started';
    const PAUSED = 'paused';
    const PAST_DUE = 'past_due';
    const ACTIVE = 'active';
    const PENDING_RENEWAL = 'pending_renewal';
    const FINISHED = 'finished';
    const CANCELED = 'canceled';
    private string $status;

    public function __construct(private Subscription $subscription)
    {
        $this->status = $this->determine();
    }

    public function __toString(): string
    {
        return $this->status;
    }

    /**
     * Gets the computed status.
     */
    public function get(): string
    {
        return $this->status;
    }

    /**
     * Computes the subscription status.
     */
    private function determine(): string
    {
        // canceled subscriptions
        if ($this->subscription->canceled) {
            return self::CANCELED;
        }

        if ($this->subscription->pending_renewal) {
            return self::PENDING_RENEWAL;
        }

        if ($this->subscription->paused) {
            return self::PAUSED;
        }

        // give it a "not started" status if it starts in the future
        // relative to the current billing period
        $startDate = $this->subscription->start_date;
        $periodStart = $this->subscription->period_start;
        if (!$periodStart) {
            $periodStart = CarbonImmutable::now()->getTimestamp();
        }
        $finished = $this->subscription->finished;
        if ($startDate > $periodStart && !$finished) {
            return self::TRIALING;
        }

        // check if the subscription has past due invoices
        // (for existing subscriptions only)
        if ($this->subscription->id) {
            $n = Invoice::where('subscription_id', $this->subscription->id)
                ->where('status', InvoiceStatus::PastDue->value)
                ->count();

            if ($n > 0) {
                return self::PAST_DUE;
            }
        }

        // finished subscriptions (finished and no past due invoices)
        if ($finished) {
            return self::FINISHED;
        }

        return self::ACTIVE;
    }
}
