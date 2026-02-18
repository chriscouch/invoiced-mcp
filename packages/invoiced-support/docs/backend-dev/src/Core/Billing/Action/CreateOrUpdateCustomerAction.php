<?php

namespace App\Core\Billing\Action;

use App\Core\Billing\BillingSystem\BillingSystemFactory;
use App\Core\Billing\Exception\BillingException;
use App\Core\Billing\Models\BillingProfile;

class CreateOrUpdateCustomerAction
{
    public function __construct(private BillingSystemFactory $billingSystemFactory)
    {
    }

    /**
     * Creates or updates a customer in the billing system. If there is no billing profile
     * provided then a new one will be created.
     *
     * @param string $billingSystemId the billing system to use as the update
     *
     * @throws BillingException
     */
    public function perform(?BillingProfile $billingProfile, string $billingSystemId, array $params): BillingProfile
    {
        if (!$billingProfile) {
            $billingProfile = new BillingProfile();
            $billingProfile->name = $params['company'];
            $billingProfile->saveOrFail();
        } elseif (isset($params['company'])) {
            $billingProfile->name = $params['company'];
            $billingProfile->saveOrFail();
        }

        $billingSystem = $this->billingSystemFactory->get($billingSystemId);
        $billingSystem->createOrUpdateCustomer($billingProfile, $params);

        return $billingProfile;
    }
}
