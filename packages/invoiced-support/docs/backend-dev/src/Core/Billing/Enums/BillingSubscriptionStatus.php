<?php

namespace App\Core\Billing\Enums;

/**
 * Represents a company's billing state for
 * use by our internal billing system.
 */
enum BillingSubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Canceled = 'canceled';
    case Unpaid = 'unpaid';

    public function isActive(): bool
    {
        return in_array($this, [self::Active, self::Trialing, self::PastDue]);
    }
}
