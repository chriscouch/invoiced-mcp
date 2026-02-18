<?php

namespace App\Core\Billing\Usage;

use App\Core\Billing\Models\OverageCharge;
use App\Core\Billing\Enums\BillingSubscriptionStatus;
use App\Core\Billing\Exception\BillingException;
use App\Core\Billing\Interfaces\BillingPeriodInterface;
use App\Core\Billing\Interfaces\UsageInterface;
use App\Core\Billing\Models\BillingProfile;
use App\Core\Billing\Models\UsagePricingPlan;
use Generator;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class OverageChargeGenerator implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private UsageFactory $usageFactory,
    ) {
    }

    /**
     * Generates overage charges for all usage pricing plans for a given period.
     * These charges returned by this process will not be persisted to the database.
     *
     * @return Generator<OverageCharge>
     */
    public function generateAllOverages(BillingPeriodInterface $billingPeriod): Generator
    {
        foreach (UsagePricingPlan::all() as $pricingPlan) {
            try {
                $usage = $this->usageFactory->get($pricingPlan->usage_type);
                if ($charge = $this->generateOverage($pricingPlan, $billingPeriod, $usage)) {
                    yield $charge;
                }
            } catch (BillingException $e) {
                $this->logger->error('Exception when generating overages', ['exception' => $e]);
            }
        }
    }

    /**
     * Generates any overage charge for usage during the billing period.
     *
     * @throws BillingException
     */
    public function generateOverage(UsagePricingPlan $pricingPlan, BillingPeriodInterface $billingPeriod, UsageInterface $usage): ?OverageCharge
    {
        // Check if the usage class supports this type of billing period
        if (!$usage->supports($billingPeriod)) {
            return null;
        }

        // Generating overage charges for a billing profile is not yet supported.
        // This will be implemented in the future.
        $company = $pricingPlan->tenant;
        if (!$company) {
            return null;
        }

        // Trialing and canceled companies cannot be charged for overages
        if (in_array($company->billingStatus(), [BillingSubscriptionStatus::Canceled, BillingSubscriptionStatus::Trialing])) {
            return null;
        }

        // Retrieve the usage within the billing period
        $usageRecord = $usage->calculateUsage($company, $billingPeriod);
        if ($usageRecord->do_not_bill) {
            return null;
        }

        // Look for an existing overage charge
        $charge = OverageCharge::queryWithTenant($company)
            ->where('month', $billingPeriod->getName())
            ->where('dimension', $pricingPlan->usage_type->getName())
            ->oneOrNull();

        if (!$charge) {
            $charge = new OverageCharge();
            $charge->tenant_id = (int) $company->id();
            $charge->month = $billingPeriod->getName();
            $charge->dimension = $pricingPlan->usage_type->getName();
            $charge->billing_system = (string) BillingProfile::getOrCreate($company)->billing_system;
        }

        // Generate a charge for the usage
        $usage->applyToCharge($pricingPlan, $usageRecord->count, $charge);

        return $charge->total > 0 ? $charge : null;
    }
}
