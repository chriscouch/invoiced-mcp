<?php

namespace App\Core\Billing\Action;

use App\Companies\Models\Company;
use App\Core\Billing\BillingSystem\ResellerBillingSystem;
use App\Core\Billing\Enums\BillingInterval;
use App\Core\Billing\Enums\BillingPaymentTerms;
use App\Core\Billing\Enums\PurchasePageReason;
use App\Core\Billing\Models\BillingProfile;
use App\Core\Billing\Models\ProductPricingPlan;
use App\Core\Billing\Models\PurchasePageContext;
use App\Core\I18n\ValueObjects\Money;
use Carbon\CarbonImmutable;

/**
 * This class can generate reactivation purchase pages
 * for a company that is in a canceled state.
 */
class ReactivatePurchasePageAction
{
    public function canReactivate(Company $company): bool
    {
        // Company must be canceled and not fraudulent
        if (!$company->canceled || $company->fraud) {
            return false;
        }

        // Cannot be a reseller account
        $billingProfile = BillingProfile::getOrCreate($company);
        if (ResellerBillingSystem::ID == $billingProfile->billing_system) {
            return false;
        }

        // Cannot be multi-entity
        $numCompanies = Company::where('billing_profile_id', $billingProfile)
            ->where('canceled', false)
            ->count();
        if ($numCompanies) {
            return false;
        }

        // Must have non-zero product pricing
        $hasNonZeroPlan = false;
        $productPricingPlans = ProductPricingPlan::forCompany($company);
        foreach ($productPricingPlans as $productPricingPlan) {
            // Any custom priced plan is immediately ineligible.
            if ($productPricingPlan->custom_pricing) {
                return false;
            }

            if ($productPricingPlan->price > 0) {
                $hasNonZeroPlan = true;
            }
        }

        return $hasNonZeroPlan;
    }

    /**
     * Checks if the company can reactivate without a purchase page.
     * This could be the case if this is a sandbox or if reactivating
     * just a single entity on a multi-entity account that is in good standing.
     */
    public function canAutoReactivate(Company $company): bool
    {
        // Company must be canceled and not fraudulent
        if (!$company->canceled || $company->fraud) {
            return false;
        }

        // Sandbox accounts can always reactivate
        $billingProfile = BillingProfile::getOrCreate($company);
        if (!$billingProfile->billing_system && $company->test_mode) {
            return true;
        }

        return false;
    }

    /**
     * Generates a reactivation purchase page for a given company.
     * This function assumes that canReactivate() has already been
     * called to see if the company is eligible for reactivation.
     */
    public function makePage(Company $company): PurchasePageContext
    {
        $billingProfile = BillingProfile::getOrCreate($company);

        // Check for an existing reactivation page
        $pageContext = PurchasePageContext::where('billing_profile_id', $billingProfile)
            ->where('reason', PurchasePageReason::Reactivate->value)
            ->where('expiration_date', CarbonImmutable::now()->toDateString(), '>=')
            ->where('completed_at', null)
            ->oneOrNull();

        if ($pageContext) {
            return $pageContext;
        }

        // Make a new page
        $pageContext = new PurchasePageContext();
        $pageContext->billing_profile = $billingProfile;
        $pageContext->tenant = $company;
        $pageContext->reason = PurchasePageReason::Reactivate;
        $pageContext->expiration_date = CarbonImmutable::now()->addDays(7);
        $pageContext->country = $company->country ?? 'US';
        $pageContext->payment_terms = BillingPaymentTerms::AutoPay;

        $productIds = [];
        $productPrices = [];
        foreach (ProductPricingPlan::forCompany($company) as $productPricingPlan) {
            if ($productPricingPlan->price > 0) {
                $productIds[] = $productPricingPlan->product_id;
                $productPrices[$productPricingPlan->product_id] = [
                    'price' => Money::fromDecimal('usd', $productPricingPlan->price)->amount,
                    'annual' => $productPricingPlan->annual,
                    'custom_pricing' => $productPricingPlan->custom_pricing,
                ];
            }
        }
        $billingInterval = $billingProfile->billing_interval;
        $pageContext->changeset = (object) [
            'features' => [],
            'products' => $productIds,
            'productPrices' => $productPrices,
            'quota' => (object) [],
            'usagePricing' => (object) [],
            'billingInterval' => $billingInterval?->value ?? BillingInterval::Monthly->value,
        ];

        $pageContext->saveOrFail();

        return $pageContext;
    }

    /**
     * Reactivates the company without performing any billing change
     * or creating a new purchase page. This should only be used if
     * canAutoReactivate() is true.
     */
    public function autoReactivate(Company $company): void
    {
        $company->canceled = false;
        $company->canceled_at = null;
        $company->canceled_reason = '';
        $company->trial_ends = null;
        $company->saveOrFail();
    }
}
