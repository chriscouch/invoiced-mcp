<?php

namespace App\Core\Billing\ValueObjects;

use App\Companies\Models\Company;
use App\Core\Billing\Enums\BillingSubscriptionStatus;
use App\Core\Billing\Models\BillingProfile;

class BillingSubscriptionStatusGenerator
{
    public static function get(Company $company): BillingSubscriptionStatus
    {
        if ($company->canceled) {
            return BillingSubscriptionStatus::Canceled;
        }

        // check if subscription is trialing
        if ($company->trial_ends > 0) {
            // If the trial end date is in the future then it
            // is considered to be trialing.
            if ($company->trial_ends > time()) {
                return BillingSubscriptionStatus::Trialing;
            }

            // When the trial has ended, the subscription has not
            // been canceled, and no future renewals are scheduled
            // then the subscription is considered `unpaid`.
            return BillingSubscriptionStatus::Unpaid;
        }

        // the subscription is past due when its status has been
        // changed to past_due
        $billingProfile = BillingProfile::getOrCreate($company);
        if ($billingProfile->past_due) {
            return BillingSubscriptionStatus::PastDue;
        }

        return BillingSubscriptionStatus::Active;
    }
}
