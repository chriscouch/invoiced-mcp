<?php

namespace App\Core\Billing\Action;

use App\Core\Billing\BillingSystem\BillingSystemFactory;
use App\Core\Billing\Enums\BillingInterval;
use App\Core\Billing\Enums\UsageType;
use App\Core\Billing\Exception\BillingException;
use App\Core\Billing\Models\BillingProfile;
use App\Core\Billing\Models\OverageCharge;
use App\Core\Billing\ValueObjects\BillingOneTimeItem;
use App\Core\I18n\ValueObjects\Money;

class BillOverageAction
{
    public function __construct(private BillingSystemFactory $billingSystemFactory)
    {
    }

    /**
     * Bills an overage charge.
     */
    public function billCharge(OverageCharge $charge): bool
    {
        // skip if this charge has already been billed
        if ($charge->billed) {
            return false;
        }

        // save the overage charge in the data store if it does not exist yet
        if (!$charge->persisted()) {
            $charge->saveOrFail();
        }

        // create the billing item
        $usageType = UsageType::fromName($charge->dimension);
        $item = new BillingOneTimeItem(
            price: Money::fromDecimal('usd', $charge->price),
            quantity: $charge->quantity,
            description: $charge->getDescription(),
            usageType: $usageType,
            billingInterval: $charge->getBillingInterval(),
            periodStart: $charge->getPeriodStart(),
            periodEnd: $charge->getPeriodEnd(),
        );

        // bill the charge on the billing system
        try {
            $billingProfile = BillingProfile::getOrCreate($charge->tenant());
            // if customer is billed infrequently, invoice immediately
            $billNow = BillingInterval::Monthly != $billingProfile->billing_interval;
            $billingSystem = $this->billingSystemFactory->getForBillingProfile($billingProfile);
            $lineItemId = $billingSystem->billLineItem($billingProfile, $item, $billNow);
        } catch (BillingException $e) {
            // record the failure reason
            $charge->failure_message = $e->getMessage();
            $charge->saveOrFail();

            return false;
        }

        // record a successful billing run
        $charge->billed = true;
        $charge->failure_message = null;
        $charge->billing_system_id = $lineItemId;
        $charge->saveOrFail();

        return true;
    }
}
