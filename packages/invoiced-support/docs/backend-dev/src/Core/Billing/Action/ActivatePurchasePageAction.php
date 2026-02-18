<?php

namespace App\Core\Billing\Action;

use App\Companies\Models\Company;
use App\Core\Billing\Enums\BillingInterval;
use App\Core\Billing\Enums\BillingPaymentTerms;
use App\Core\Billing\Enums\PurchasePageReason;
use App\Core\Billing\Enums\UsageType;
use App\Core\Billing\Exception\BillingException;
use App\Core\Billing\Models\BillingProfile;
use App\Core\Billing\Models\PurchasePageContext;
use App\Core\Entitlements\Enums\QuotaType;
use App\Core\Entitlements\Models\Product;
use App\Core\I18n\ValueObjects\Money;
use Carbon\CarbonImmutable;

/**
 * This class can generate reactivation purchase pages
 * for a company that is in a canceled state.
 */
class ActivatePurchasePageAction
{
    private const PLANS = [
        'growth' => [
            'name' => 'Growth',
            'prices' => [
                'yearly' => [
                    'price' => 11988,
                    'users' => 5,
                    'user_price' => 39,
                    'invoices' => 250,
                    'invoice_price' => 1,
                ],
                'monthly' => [
                    'price' => 1199,
                    'users' => 5,
                    'user_price' => 39,
                    'invoices' => 250,
                    'invoice_price' => 1,
                ],
            ],
        ],
    ];

    public function __construct(private LocalizedPricingAdjustment $pricingAdjuster)
    {
    }

    public function canActivate(Company $company): bool
    {
        return $company->trial_ends > 0 && $company->features->has('not_activated');
    }

    public function getPrice(string $plan, BillingInterval $billingInterval, ?string $type = null): Money
    {
        // Look up the price for the selected plan and billing interval
        $planDetails = self::PLANS[$plan] ?? null;
        if (!$planDetails) {
            throw new BillingException('Plan not recognized: '.$plan);
        }

        $priceDetails = $planDetails['prices'][$billingInterval->getIdName()] ?? null;
        if (!$priceDetails) {
            throw new BillingException('Billing interval not supported: '.$billingInterval->getIdName());
        }

        if ('invoice' == $type) {
            return Money::fromDecimal('usd', $priceDetails['invoice_price']);
        }

        if ('user' == $type) {
            return Money::fromDecimal('usd', $priceDetails['user_price']);
        }

        return Money::fromDecimal('usd', $priceDetails['price']);
    }

    /**
     * Gets all prices for display on the activation page.
     * These prices are normalized with PPP adjustment
     * and to monthly amounts.
     */
    public function getAllPrices(string $country): array
    {
        $localizedAdjustment = $this->pricingAdjuster->getLocalizedAdjustment($country);

        $prices = [];
        foreach (self::PLANS as $id => $plan) {
            foreach ($plan['prices'] as $interval => $priceDetails) {
                if ('yearly' == $interval) {
                    $priceDetails['price'] = $priceDetails['price'] / 12;
                }

                $prices[$id.'-'.$interval] = [
                    'price' => $this->pricingAdjuster->applyAdjustment(Money::fromDecimal('usd', $priceDetails['price']), $localizedAdjustment),
                    'invoice' => $this->pricingAdjuster->applyAdjustment(Money::fromDecimal('usd', $priceDetails['invoice_price']), $localizedAdjustment),
                    'user' => $this->pricingAdjuster->applyAdjustment(Money::fromDecimal('usd', $priceDetails['user_price']), $localizedAdjustment),
                ];
            }
        }

        return $prices;
    }

    /**
     * Generates an activation purchase page for a given company.
     * This function assumes that canReactivate() has already been
     * called to see if the company is eligible for reactivation.
     */
    public function makePage(Company $company, string $plan, BillingInterval $billingInterval): PurchasePageContext
    {
        $billingProfile = BillingProfile::getOrCreate($company);

        // Check for an existing activation page
        $pageContext = PurchasePageContext::where('billing_profile_id', $billingProfile)
            ->where('tenant_id', $company)
            ->where('reason', PurchasePageReason::Activate->value)
            ->where('expiration_date', CarbonImmutable::now()->toDateString(), '>=')
            ->where('sales_rep', null)
            ->where('completed_at', null)
            ->oneOrNull();

        // Make a new page
        if (!$pageContext) {
            $pageContext = new PurchasePageContext();
            $pageContext->billing_profile = $billingProfile;
            $pageContext->tenant = $company;
            $pageContext->reason = PurchasePageReason::Activate;
        }

        $pageContext->expiration_date = CarbonImmutable::now()->addDays(7);
        $pageContext->country = $company->country ?? 'US';
        $pageContext->payment_terms = BillingPaymentTerms::AutoPay;
        $pageContext->localized_pricing = true;

        // Look up the product and price for the selected plan and billing interval
        $planId = $plan;
        $planDetails = self::PLANS[$planId] ?? null;
        if (!$planDetails) {
            throw new BillingException('Plan not recognized: '.$planId);
        }

        $product = Product::where('name', $planDetails['name'])->oneOrNull();
        if (!$product) {
            throw new BillingException('Product does not exist: '.$planDetails['name']);
        }

        $priceDetails = $planDetails['prices'][$billingInterval->getIdName()] ?? null;
        if (!$priceDetails) {
            throw new BillingException('Billing interval not supported: '.$billingInterval->getIdName());
        }

        $productIds = [$product->id];
        $productPrices = [
            $product->id => [
                'price' => $this->getPrice($plan, $billingInterval)->amount,
                'annual' => BillingInterval::Yearly == $billingInterval,
                'custom_pricing' => false,
            ],
        ];

        // Establish usage limits
        $quota = (object) [
            QuotaType::Users->getName() => $priceDetails['users'],
        ];
        $usagePrices = (object) [
            UsageType::Users->getName() => [
                'threshold' => $priceDetails['users'],
                'unit_price' => $this->getPrice($plan, $billingInterval, 'user')->amount,
            ],
        ];

        $k = UsageType::InvoicesPerMonth->getName();
        $usagePrices->$k = [
            'threshold' => $priceDetails['invoices'],
            'unit_price' => $this->getPrice($plan, $billingInterval, 'invoice')->amount,
        ];
        $k = QuotaType::CustomerEmailDailyLimit->getName();
        $quota->$k = $priceDetails['invoices'];

        $pageContext->changeset = (object) [
            'features' => [],
            'products' => $productIds,
            'productPrices' => $productPrices,
            'replaceExistingProducts' => true,
            'quota' => $quota,
            'usagePricing' => $usagePrices,
            'billingInterval' => $billingInterval->value,
        ];

        $pageContext->saveOrFail();

        return $pageContext;
    }
}
