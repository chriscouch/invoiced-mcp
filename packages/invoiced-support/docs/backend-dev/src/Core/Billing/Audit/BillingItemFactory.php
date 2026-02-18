<?php

namespace App\Core\Billing\Audit;

use App\Companies\Models\Company;
use App\Core\Billing\Enums\BillingInterval;
use App\Core\Billing\Enums\UsageType;
use App\Core\Billing\Exception\BillingException;
use App\Core\Billing\Models\BillingProfile;
use App\Core\Billing\Models\ProductPricingPlan;
use App\Core\Billing\Models\UsagePricingPlan;
use App\Core\Billing\ValueObjects\BillingSubscriptionItem;
use App\Core\Entitlements\Enums\QuotaType;
use App\Core\Entitlements\Models\InstalledProduct;
use App\Core\Entitlements\Models\Product;
use App\Core\I18n\ValueObjects\Money;

class BillingItemFactory
{
    private array $products = [];

    /**
     * Generates the subscription items that should be billed to an account.
     *
     * @throws BillingException
     *
     * @return BillingSubscriptionItem[]
     */
    public function generateItems(BillingProfile $billingProfile): array
    {
        $subscriptionItems = [];
        $toBillingInterval = $billingProfile->billing_interval;
        if (!$toBillingInterval) {
            throw new BillingException('The billing interval is not set on the billing profile');
        }

        // Get all non-canceled companies for this billing profile
        $companies = Company::where('billing_profile_id', $billingProfile)
            ->where('canceled', false)
            ->all()
            ->toArray();

        // Check products and users on each company
        foreach ($companies as $company) {
            // Products
            // First gather list of installed products
            $installedProductIds = [];
            $installedProducts = InstalledProduct::queryWithTenant($company)->first(100);
            foreach ($installedProducts as $installedProduct) {
                $installedProductIds[] = $installedProduct->product_id;
            }

            // Then check the latest product pricing plans per installed product
            $productPricing = [];
            $productPricingPlans = ProductPricingPlan::forCompany($company);
            foreach ($productPricingPlans as $productPricingPlan) {
                // Only consider pricing for installed products
                $productId = $productPricingPlan->product_id;
                if (!in_array($productId, $installedProductIds)) {
                    continue;
                }

                // Generate line item to go on subscription
                $productPricing[$productId] = $productPricingPlan;
                if ($productPricingPlan->price) {
                    $fromBillingInterval = $productPricingPlan->annual ? BillingInterval::Yearly : BillingInterval::Monthly;
                    $subscriptionItems[] = new BillingSubscriptionItem(
                        price: $this->normalizeToBillingInterval($productPricingPlan->price, $fromBillingInterval, $toBillingInterval),
                        billingInterval: $toBillingInterval,
                        product: $this->getProduct($productId),
                        description: $company->name,
                        customPricing: $productPricingPlan->custom_pricing,
                    );
                }
            }

            // Verify that every installed product has pricing
            foreach ($installedProductIds as $productId) {
                if (!isset($productPricing[$productId])) {
                    throw new BillingException($company->name.' (# '.$company->id.') does not have product pricing for '.$this->getProduct($productId)->name);
                }
            }

            // Extra users
            $userPricing = UsagePricingPlan::where('tenant_id', $company)
                ->where('usage_type', UsageType::Users->value)
                ->oneOrNull();
            if ($userPricing) {
                $perUserPrice = $userPricing->unit_price;
                $includedUsers = $userPricing->threshold;
                $actualUsers = $company->quota->get(QuotaType::Users);
                $quantity = (int) max(0, $actualUsers - $includedUsers);

                // Generate line item to go on subscription
                if ($perUserPrice > 0 && $quantity > 0) {
                    $subscriptionItems[] = new BillingSubscriptionItem(
                        price: $this->normalizeToBillingInterval($perUserPrice, BillingInterval::Monthly, $toBillingInterval),
                        billingInterval: $toBillingInterval,
                        usageType: UsageType::Users,
                        quantity: $quantity,
                        description: $company->name,
                    );
                }
            }
        }

        // Total entities
        $entityPricing = UsagePricingPlan::where('billing_profile_id', $billingProfile)
            ->where('usage_type', UsageType::Entities->value)
            ->oneOrNull();
        if ($entityPricing) {
            $totalEntities = count($companies);
            $includedEntities = $entityPricing->threshold;
            $perEntityPrice = $entityPricing->unit_price;
            $quantity = (int) max(0, $totalEntities - $includedEntities);

            // Generate line item to go on subscription
            if ($perEntityPrice > 0 && $quantity > 0) {
                $subscriptionItems[] = new BillingSubscriptionItem(
                    price: $this->normalizeToBillingInterval($perEntityPrice, BillingInterval::Monthly, $toBillingInterval),
                    billingInterval: $toBillingInterval,
                    usageType: UsageType::Entities,
                    quantity: $quantity,
                );
            }
        }

        // If an account does not have any subscription items then this
        // implies that they have PAYG pricing for $0
        if (0 == count($subscriptionItems)) {
            $subscriptionItems[] = new BillingSubscriptionItem(
                price: Money::zero('usd'),
                billingInterval: $toBillingInterval,
            );
        }

        return $subscriptionItems;
    }

    /**
     * Calculates the total that a billing profile is billed
     * normalized to the given billing interval.
     *
     * @throws BillingException
     */
    public function calculateTotal(BillingProfile $billingProfile, BillingInterval $billingInterval = BillingInterval::Monthly): Money
    {
        $subscriptionItems = $this->generateItems($billingProfile);
        $total = Money::zero('usd');
        foreach ($subscriptionItems as $subscriptionItem) {
            $monthlyPrice = $this->normalizeToBillingInterval($subscriptionItem->total->toDecimal(), $subscriptionItem->billingInterval, $billingInterval);
            $total = $total->add($monthlyPrice);
        }

        return $total;
    }

    private function getProduct(int $id): Product
    {
        if (!isset($this->products[$id])) {
            $this->products[$id] = Product::findOrFail($id);
        }

        return $this->products[$id];
    }

    /**
     * Normalizes a price from one billing interval to another.
     * Eg. monthly to yearly.
     */
    private function normalizeToBillingInterval(float $price, BillingInterval $from, BillingInterval $to): Money
    {
        // Calculate the conversion factor by number of months in interval
        return Money::fromDecimal('usd', $price / $from->numMonths() * $to->numMonths());
    }
}
