<?php

namespace App\Core\Billing\Action;

use App\Companies\Libs\CompanyEntitlementsManager;
use App\Companies\Models\Company;
use App\Companies\ValueObjects\EntitlementsChangeset;
use App\Core\Billing\Audit\BillingItemFactory;
use App\Core\Billing\BillingSystem\BillingSystemFactory;
use App\Core\Billing\Enums\BillingInterval;
use App\Core\Billing\Exception\BillingException;
use App\Core\Billing\Models\BillingProfile;
use Carbon\CarbonImmutable;

class CreateSubscriptionAction
{
    public function __construct(
        private BillingSystemFactory $factory,
        private BillingItemFactory $billingItemFactory,
        private CompanyEntitlementsManager $entitlementsManager,
    ) {
    }

    /**
     * Creates a new subscription for the company.
     *
     * @throws BillingException when the operation fails
     */
    public function create(Company $company, BillingInterval $billingInterval, EntitlementsChangeset $changeset, ?CarbonImmutable $startDate = null): void
    {
        $startDate ??= CarbonImmutable::now();

        // Make the change in entitlements
        $this->entitlementsManager->applyChangeset($company, $changeset);

        // Update the company's billing state
        $billingProfile = BillingProfile::getOrCreate($company);
        $this->updateModels($billingProfile, $company, $billingInterval);

        // Generate the subscription items
        $subscriptionItems = $this->billingItemFactory->generateItems($billingProfile);

        // Execute the change in the billing system
        $billingSystem = $this->factory->getForBillingProfile($billingProfile);
        $billingSystem->createSubscription($billingProfile, $subscriptionItems, $startDate);
    }

    private function updateModels(BillingProfile $billingProfile, Company $company, BillingInterval $billingInterval): void
    {
        $company->canceled = false;
        $company->canceled_at = null;
        $company->canceled_reason = '';
        $company->trial_ends = null;
        $company->converted_from = $company->trial_started > 0 ? 'trial' : '';
        $company->converted_at = time();
        $company->saveOrFail();

        $billingProfile->billing_interval = $billingInterval;
        $billingProfile->past_due = false;
        $billingProfile->saveOrFail();
    }
}
