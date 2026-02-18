<?php

namespace App\Core\Billing\BillingSystem;

use App\Core\Billing\Exception\BillingException;
use App\Core\Billing\Models\BillingProfile;
use App\Core\Billing\ValueObjects\BillingOneTimeItem;
use App\Core\Billing\ValueObjects\BillingSystemSubscription;
use Carbon\CarbonImmutable;

class NullBillingSystem extends AbstractBillingSystem
{
    //
    // BillingSystemInterface
    //

    public function createOrUpdateCustomer(BillingProfile $billingProfile, array $params): void
    {
        // there's nothing to do here
    }

    public function createSubscription(BillingProfile $billingProfile, array $subscriptionItems, CarbonImmutable $startDate): void
    {
        throw new BillingException('Creating a new subscription is not supported without a billing system selected.');
    }

    public function updateSubscription(BillingProfile $billingProfile, array $subscriptionItems, bool $prorate, CarbonImmutable $prorationDate): void
    {
        throw new BillingException('Updating a subscription is not supported without a billing system selected.');
    }

    public function setDefaultPaymentMethod(BillingProfile $billingProfile, string $token): void
    {
        throw new BillingException('Setting default payment method not supported without a billing system selected.');
    }

    public function billLineItem(BillingProfile $billingProfile, BillingOneTimeItem $item, bool $billNow): string
    {
        // there's nothing to do here
        return '';
    }

    public function cancel(BillingProfile $billingProfile, bool $atPeriodEnd): void
    {
        // canceling at period end is not supported because there is no billing period
        if ($atPeriodEnd) {
            throw new BillingException('Canceling at billing period end not supported. Please select the cancel immediately option.');
        }
    }

    public function reactivate(BillingProfile $billingProfile): void
    {
        // there's nothing to do here
    }

    public function getBillingHistory(BillingProfile $billingProfile): array
    {
        // there's nothing to do here
        return [];
    }

    //
    // AbstractBillingSystem
    //

    public function getPaymentSourceInfo(BillingProfile $billingProfile): array
    {
        // there's nothing to do here
        return [];
    }

    public function getDiscount(BillingProfile $billingProfile): ?array
    {
        // there's nothing to do here
        return null;
    }

    public function isCanceledAtPeriodEnd(BillingProfile $billingProfile): bool
    {
        // there's nothing to do here
        return false;
    }

    public function getNextChargeAmount(BillingProfile $billingProfile): float
    {
        // there's nothing to do here
        return 0.0;
    }

    public function isAutoPay(BillingProfile $billingProfile): bool
    {
        return false;
    }

    public function getNextBillDate(BillingProfile $billingProfile): ?CarbonImmutable
    {
        return null;
    }

    public function getUpdatePaymentInfoUrl(BillingProfile $billingProfile): ?string
    {
        return null;
    }

    public function getCurrentSubscription(BillingProfile $billingProfile): BillingSystemSubscription
    {
        throw new BillingException('There is no current subscription without a billing system selected.');
    }
}
