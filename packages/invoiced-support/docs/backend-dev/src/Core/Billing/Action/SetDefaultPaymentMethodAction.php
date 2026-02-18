<?php

namespace App\Core\Billing\Action;

use App\Core\Billing\BillingSystem\BillingSystemFactory;
use App\Core\Billing\Exception\BillingException;
use App\Core\Billing\Models\BillingProfile;

class SetDefaultPaymentMethodAction
{
    public function __construct(private BillingSystemFactory $factory)
    {
    }

    /**
     * Sets the default payment method on file. If
     * there is an existing payment method, it will be deleted
     * and replaced with the new one.
     *
     * @throws BillingException
     */
    public function set(BillingProfile $billingProfile, string $token): void
    {
        if (empty($token)) {
            throw new BillingException('Cannot set default payment method because payment method token is missing.');
        }

        $billingSystem = $this->factory->getForBillingProfile($billingProfile);
        $billingSystem->setDefaultPaymentMethod($billingProfile, $token);
    }
}
