<?php

namespace App\Core\Billing\Action;

use App\Companies\Libs\CompanyEntitlementsManager;
use App\Companies\Models\Company;
use App\Companies\ValueObjects\EntitlementsChangeset;
use App\Core\Billing\Audit\BillingAudit;
use App\Core\Billing\Audit\BillingItemFactory;
use App\Core\Billing\BillingSystem\BillingSystemFactory;
use App\Core\Billing\Exception\BillingException;
use App\Core\Billing\Models\BillingProfile;
use Carbon\CarbonImmutable;

class ChangeSubscriptionAction
{
    public function __construct(
        private BillingSystemFactory $factory,
        private BillingItemFactory $billingItemFactory,
        private BillingAudit $billingAudit,
        private CompanyEntitlementsManager $entitlementsManager,
    ) {
    }

    /**
     * Performs a change to the company's subscription.
     *
     * @throws BillingException when the operation fails
     */
    public function change(Company $company, EntitlementsChangeset $changeset, bool $prorate = true, ?CarbonImmutable $prorationDate = null): void
    {
        $prorationDate ??= CarbonImmutable::now();

        // Check that the subscription in the database matches the billing system
        $billingProfile = BillingProfile::getOrCreate($company);
        if (!$this->billingAudit->audit($billingProfile, false)) {
            throw new BillingException('Unable to perform this change because the subscription in the billing system did not match.');
        }

        // Make the change in entitlements
        $this->entitlementsManager->applyChangeset($company, $changeset);

        // Update the company billing state
        $this->updateModels($billingProfile, $company);

        // Generate the subscription items
        $subscriptionItems = $this->billingItemFactory->generateItems($billingProfile);

        // Execute the change in the billing system
        $billingSystem = $this->factory->getForBillingProfile($billingProfile);
        $billingSystem->updateSubscription($billingProfile, $subscriptionItems, $prorate, $prorationDate);
    }

    private function updateModels(BillingProfile $billingProfile, Company $company): void
    {
        $company->canceled = false;
        $company->canceled_at = null;
        $company->canceled_reason = '';
        $company->trial_ends = null;
        $company->saveOrFail();

        $billingProfile->past_due = false;
        $billingProfile->saveOrFail();
    }
}
