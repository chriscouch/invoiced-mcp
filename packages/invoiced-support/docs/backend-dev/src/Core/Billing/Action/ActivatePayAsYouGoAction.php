<?php

namespace App\Core\Billing\Action;

use App\Companies\Enums\VerificationStatus;
use App\Companies\Models\Company;
use App\Companies\Models\CompanyEmailAddress;
use App\Core\Billing\Audit\BillingItemFactory;
use App\Core\Billing\BillingSystem\InvoicedBillingSystem;
use App\Core\Billing\Enums\BillingInterval;
use App\Core\Billing\Enums\UsageType;
use App\Core\Billing\Exception\BillingException;
use App\Core\Billing\Models\BillingProfile;
use App\Core\Billing\Models\ProductPricingPlan;
use App\Core\Billing\Models\UsagePricingPlan;
use App\Core\Entitlements\Enums\QuotaType;
use App\Core\Entitlements\Models\InstalledProduct;
use App\Core\Entitlements\Models\Product;
use App\Core\Entitlements\ProductInstaller;
use Carbon\CarbonImmutable;

class ActivatePayAsYouGoAction
{
    private const REMOVE_FEATURES = ['phone_support', 'live_chat', 'api', 'not_activated', 'network_invitations'];
    private const REMOVE_QUOTAS = [QuotaType::Users, QuotaType::TransactionsPerDay];

    public function __construct(
        private InvoicedBillingSystem $invoicedBillingSystem,
        private BillingItemFactory $billingItemFactory,
        private ProductInstaller $installer,
    ) {
    }

    /**
     * Activates a free account that was created via the sign up process
     * to a pay-as-you-go account. This is not intended to convert paying
     * customers to the pay-as-you-go tier.
     *
     * @throws BillingException
     */
    public function activate(Company $company): void
    {
        if (!$company->features->has('not_activated')) {
            throw new BillingException('This account is not eligible for the pay-as-you-go tier.');
        }

        if (VerificationStatus::Verified != CompanyEmailAddress::getVerificationStatus($company)) {
            throw new BillingException('You must first validate your email address in Settings > Business Profile.');
        }

        // Set the billing interval on the billing profile
        $billingProfile = BillingProfile::getOrCreate($company);
        $billingProfile->billing_interval = BillingInterval::Monthly;
        // intentionally not saving because this will be done after the billing system action

        // Create a usage pricing plan for 1%
        $pricingPlan = new UsagePricingPlan();
        $pricingPlan->usage_type = UsageType::MoneyBilledPerMonth;
        $pricingPlan->tenant = $company;
        $pricingPlan->threshold = 500; // Do not bill anything less than $5
        $pricingPlan->unit_price = 0.01; // 1%
        $pricingPlan->saveOrFail();

        // Remove certain feature flags set during sign up
        foreach (self::REMOVE_FEATURES as $feature) {
            $company->features->remove($feature);
        }

        // Remove quotas set during sign up
        foreach (self::REMOVE_QUOTAS as $quota) {
            $company->quota->remove($quota);
        }

        // Uninstall all products and install the Advanced A/R product
        InstalledProduct::queryWithTenant($company)->delete();
        $product = Product::where('name', 'Advanced Accounts Receivable')->one();
        $this->installer->install($product, $company);
        $productPricing = new ProductPricingPlan();
        $productPricing->tenant = $company;
        $productPricing->product = $product;
        $productPricing->price = 0;
        $productPricing->effective_date = CarbonImmutable::now();
        $productPricing->posted_on = CarbonImmutable::now();
        $productPricing->saveOrFail();

        // Generate the subscription items
        $subscriptionItems = $this->billingItemFactory->generateItems($billingProfile);

        // Execute the change in the billing system
        $this->invoicedBillingSystem->createSubscription($billingProfile, $subscriptionItems, CarbonImmutable::now());

        // Update company and billing profile properties
        $this->updateModels($company, $billingProfile);
    }

    private function updateModels(Company $company, BillingProfile $billingProfile): void
    {
        $company->converted_from = 'free';
        $company->converted_at = time();
        $company->canceled = false;
        $company->canceled_at = null;
        $company->canceled_reason = '';
        $company->trial_ends = null;
        $company->saveOrFail();

        $billingProfile->past_due = false;
        $billingProfile->saveOrFail();
    }
}
