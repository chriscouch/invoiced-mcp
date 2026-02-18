<?php

namespace App\Companies\Libs;

use App\Companies\Exception\NewCompanySignUpException;
use App\Companies\Models\Company;
use App\Companies\ValueObjects\EntitlementsChangeset;
use App\Core\Billing\Enums\UsageType;
use App\Core\Billing\Models\ProductPricingPlan;
use App\Core\Billing\Models\UsagePricingPlan;
use App\Core\Entitlements\Enums\QuotaType;
use App\Core\Entitlements\Exception\InstallProductException;
use App\Core\Entitlements\Models\InstalledProduct;
use App\Core\Entitlements\ProductInstaller;
use Carbon\CarbonImmutable;

class CompanyEntitlementsManager
{
    public function __construct(
        private ProductInstaller $productInstaller,
    ) {
    }

    /**
     * Applies a set of entitlement changes to a company.
     *
     * @throws NewCompanySignUpException
     */
    public function applyChangeset(Company $company, EntitlementsChangeset $changeset): void
    {
        // Remove existing products
        if ($changeset->replaceExistingProducts) {
            InstalledProduct::queryWithTenant($company)->delete();
            ProductPricingPlan::where('tenant_id', $company)->delete();
        }

        // Install products
        foreach ($changeset->products as $product) {
            try {
                $this->productInstaller->install($product, $company);
            } catch (InstallProductException $e) {
                throw new NewCompanySignUpException($e->getMessage(), $e->getCode(), $e);
            }

            // Create or update a new product pricing plan
            $productPricing = ProductPricingPlan::where('tenant_id', $company)
                ->where('product_id', $product)
                ->where('effective_date', CarbonImmutable::now()->toDateString())
                ->oneOrNull();
            if (!$productPricing) {
                $productPricing = new ProductPricingPlan();
                $productPricing->tenant = $company;
                $productPricing->product = $product;
                $productPricing->effective_date = CarbonImmutable::now();
            }
            if ($price = $changeset->getProductPrice($product)) {
                $productPricing->price = $price['price']->toDecimal();
                $productPricing->annual = $price['annual'] ?? false;
                $productPricing->custom_pricing = $price['custom_pricing'] ?? false;
            } else {
                $productPricing->price = 0;
                $productPricing->annual = false;
                $productPricing->custom_pricing = false;
            }
            $productPricing->posted_on = CarbonImmutable::now();
            $productPricing->saveOrFail();
        }

        // Apply feature overrides
        foreach ($changeset->features as $feature => $enabled) {
            if ($enabled) {
                $company->features->enable($feature);
            } else {
                $company->features->disable($feature);
            }
        }

        // Apply quotas
        $quota = $changeset->quota;
        foreach ($quota as $key => $value) {
            $company->quota->set(QuotaType::fromString($key), $value);
        }

        // Usage pricing plans
        foreach ($changeset->usagePricing as $usageTypeName => $usagePrice) {
            // Check for an existing usage pricing plan
            $usageType = UsageType::fromName($usageTypeName);
            $pricingPlan = UsagePricingPlan::where('tenant_id', $company)
                ->where('usage_type', $usageType->value)
                ->oneOrNull();

            if (!$pricingPlan) {
                $pricingPlan = new UsagePricingPlan();
                $pricingPlan->usage_type = $usageType;
                $pricingPlan->tenant = $company;
            }

            $pricingPlan->threshold = $usagePrice['threshold'];
            $pricingPlan->unit_price = $usagePrice['unit_price']->toDecimal();
            $pricingPlan->saveOrFail();
        }
    }
}
