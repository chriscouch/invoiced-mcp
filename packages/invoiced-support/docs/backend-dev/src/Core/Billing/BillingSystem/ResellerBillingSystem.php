<?php

namespace App\Core\Billing\BillingSystem;

use App\Core\Billing\Exception\BillingException;
use App\Core\Billing\Models\BillingProfile;
use App\Core\Billing\ValueObjects\BillingOneTimeItem;
use Carbon\CarbonImmutable;

class ResellerBillingSystem extends InvoicedBillingSystem
{
    const ID = 'reseller';

    //
    // BillingSystemInterface
    //

    public function createOrUpdateCustomer(BillingProfile $billingProfile, array $params): void
    {
        throw new BillingException('Please contact your reseller for billing assistance.');
    }

    public function createSubscription(BillingProfile $billingProfile, array $subscriptionItems, CarbonImmutable $startDate): void
    {
        throw new BillingException('Please contact your reseller for billing assistance.');
    }

    public function setDefaultPaymentMethod(BillingProfile $billingProfile, string $token): void
    {
        throw new BillingException('Please contact your reseller for billing assistance.');
    }

    public function cancel(BillingProfile $billingProfile, bool $atPeriodEnd): void
    {
        if ($atPeriodEnd) {
            throw new BillingException('Canceling reseller billed accounts at the end of the billing period is not currently supported. You must select the cancel immediately option.');
        }
    }

    public function getUpdatePaymentInfoUrl(BillingProfile $billingProfile): ?string
    {
        return null;
    }

    public function billLineItem(BillingProfile $billingProfile, BillingOneTimeItem $item, bool $billNow): string
    {
        // there's nothing to do here
        return '';
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
}
