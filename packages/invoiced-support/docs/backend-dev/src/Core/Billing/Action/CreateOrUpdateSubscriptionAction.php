<?php

namespace App\Core\Billing\Action;

use App\Companies\Models\Company;
use App\Companies\ValueObjects\EntitlementsChangeset;
use App\Core\Billing\BillingSystem\BillingSystemFactory;
use App\Core\Billing\Enums\BillingInterval;
use App\Core\Billing\Exception\BillingException;
use App\Core\Billing\Models\BillingProfile;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use Carbon\CarbonImmutable;

class CreateOrUpdateSubscriptionAction implements StatsdAwareInterface
{
    use StatsdAwareTrait;

    public function __construct(
        private BillingSystemFactory $factory,
        private CreateSubscriptionAction $create,
        private ChangeSubscriptionAction $update,
    ) {
    }

    /**
     * Creates a new subscription or modifies the existing subscription for the company.
     *
     * @throws BillingException when the operation fails
     */
    public function perform(Company $company, BillingInterval $billingInterval, EntitlementsChangeset $changeset, ?CarbonImmutable $startDate = null): void
    {
        $wasTrialing = $company->trial_ends > 0;

        // check if there is an existing subscription for this billing profile
        $existingSubscriber = false;
        $billingProfile = BillingProfile::getOrCreate($company);
        try {
            $this->factory->getForBillingProfile($billingProfile)
                ->getCurrentSubscription($billingProfile);
            $existingSubscriber = true;
        } catch (BillingException) {
            // An exception means the subscription does not exist
        }

        if ($existingSubscriber) {
            $this->update->change($company, $changeset, true, $startDate);
        } else {
            $this->create->create($company, $billingInterval, $changeset, $startDate);
        }

        if ($wasTrialing) {
            $this->statsd->increment('trial_funnel.complete_purchase', 1.0, ['product' => str_replace(' ', '_', $changeset->products[0]->name), 'billing_interval' => $billingInterval->getIdName()]);
        }
    }
}
