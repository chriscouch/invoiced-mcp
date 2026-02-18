<?php

namespace App\Core\Billing\Action;

use App\Companies\Models\Company;
use App\Companies\Models\Member;
use App\Core\Billing\Audit\BillingAudit;
use App\Core\Billing\Audit\BillingItemFactory;
use App\Core\Billing\BillingSystem\BillingSystemFactory;
use App\Core\Billing\Enums\BillingSubscriptionStatus;
use App\Core\Billing\Enums\UsageType;
use App\Core\Billing\Exception\BillingException;
use App\Core\Billing\Models\BillingProfile;
use App\Core\Billing\Models\UsagePricingPlan;
use App\Core\Entitlements\Enums\QuotaType;
use Carbon\CarbonImmutable;

class ChangeExtraUserCountAction
{
    public function __construct(
        private BillingSystemFactory $factory,
        private BillingItemFactory $billingItemFactory,
        private BillingAudit $billingAudit,
    ) {
    }

    /**
     * Sets the number of extra users and updates
     * the billing and entitlements accordingly.
     *
     * @throws BillingException
     */
    public function change(Company $company, int $count): void
    {
        if ($count < 0) {
            throw new BillingException('Additional user count cannot be negative.');
        }

        // Only existing subscriptions can be modified.
        if (!in_array($company->billingStatus(), [BillingSubscriptionStatus::Active, BillingSubscriptionStatus::PastDue])) {
            throw new BillingException('There is no existing subscription to modify.');
        }

        $userPricing = UsagePricingPlan::where('tenant_id', $company)
            ->where('usage_type', UsageType::Users->value)
            ->oneOrNull();
        if (!$userPricing) {
            throw new BillingException('The extra user count cannot be changed without a user pricing plan.');
        }

        // Check if change would result in more active users than current users
        $includedUsers = $userPricing->threshold;
        $numUsers = Member::where('expires', 0)->count();
        $newUserLimit = $includedUsers + $count;
        if ($newUserLimit < $numUsers) {
            throw new BillingException('Cannot reduce allowed user count below current number of users.');
        }

        // Check that the subscription in the database matches the billing system
        $billingProfile = BillingProfile::getOrCreate($company);
        if (!$this->billingAudit->audit($billingProfile, false)) {
            throw new BillingException('Unable to perform this change because the subscription in the billing system did not match.');
        }

        // Set the user quota to the new limit
        $company->quota->set(QuotaType::Users, $newUserLimit);

        // Generate the subscription items
        $subscriptionItems = $this->billingItemFactory->generateItems($billingProfile);

        // Execute the change in the billing system with prorations enabled
        $billingSystem = $this->factory->getForBillingProfile($billingProfile);
        $billingSystem->updateSubscription($billingProfile, $subscriptionItems, true, CarbonImmutable::now());
    }
}
