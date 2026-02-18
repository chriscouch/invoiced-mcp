<?php

namespace App\Core\Billing\Action;

use App\Companies\Models\Company;
use App\Core\Billing\BillingSystem\BillingSystemFactory;
use App\Core\Billing\Exception\BillingException;
use App\Core\Billing\Models\BillingProfile;
use App\Core\Mailer\Mailer;

class CancelSubscriptionAction
{
    public function __construct(
        private BillingSystemFactory $factory,
        private Mailer $mailer,
    ) {
    }

    /**
     * Cancels the subscription in the billing system and sends
     * the user a notification about the cancellation.
     *
     * @param bool $atPeriodEnd when true, cancels the subscription at the end of the billing period
     *
     * @throws BillingException when the operation fails
     */
    public function cancel(Company $company, string $reason, bool $atPeriodEnd = false): void
    {
        if (!$company->billingStatus()->isActive()) {
            throw new BillingException('This account has already been canceled');
        }

        // cancel in the billing system
        $billingProfile = BillingProfile::getOrCreate($company);
        $billingSystem = $this->factory->getForBillingProfile($billingProfile);
        // we override $atPeriodEnd because it should have affect only if billing system is set up
        $atPeriodEnd = $billingProfile->billing_system && $atPeriodEnd;
        $billingSystem->cancel($billingProfile, $atPeriodEnd);

        // update our database with the cancellation status
        // if there is no billing system - company should be cancelled immediately
        $this->updateModels($company, $reason, $atPeriodEnd);

        // send a cancellation email to the company
        $this->mailer->sendToAdministrators(
            $company,
            [
                'subject' => 'Your Invoiced account has been canceled',
            ],
            'subscription-canceled',
            [
                'company' => $company->name,
            ],
        );
    }

    private function updateModels(Company $company, string $reason, bool $atPeriodEnd): void
    {
        if (!$atPeriodEnd) {
            $company->canceled = true;
            $company->canceled_at = time();
        }

        if ($reason) {
            $company->canceled_reason = $reason;
        }

        $company->saveOrFail();
    }
}
