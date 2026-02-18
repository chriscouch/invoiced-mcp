<?php

namespace App\Core\Billing\Action;

use App\Companies\Models\Company;
use App\Core\Billing\BillingSystem\BillingSystemFactory;
use App\Core\Billing\Exception\BillingException;
use App\Core\Billing\Models\BillingProfile;

class ReactivateSubscriptionAction
{
    public function __construct(private BillingSystemFactory $factory)
    {
    }

    /**
     * Reactivates a subscription that is marked for
     * cancellation at the end of the current billing period.
     *
     * @throws BillingException
     */
    public function reactivate(Company $company): void
    {
        if (!$company->billingStatus()->isActive()) {
            throw new BillingException('This account has already been canceled and cannot be reactivated');
        }

        $billingProfile = BillingProfile::getOrCreate($company);
        $billingSystem = $this->factory->getForBillingProfile($billingProfile);
        $billingSystem->reactivate($billingProfile);

        $company->canceled_at = null;
        $company->canceled_reason = '';
        $company->converted_from = 'canceled';
        $company->converted_at = time();
        $company->saveOrFail();
    }
}
