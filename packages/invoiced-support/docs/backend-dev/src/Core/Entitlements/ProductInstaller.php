<?php

namespace App\Core\Entitlements;

use App\AccountsPayable\AccountsPayableProduct;
use App\AccountsReceivable\AccountsReceivableProduct;
use App\CustomerPortal\CustomerPortalProduct;
use App\CashApplication\CashApplicationProduct;
use App\Companies\Models\Company;
use App\Core\Entitlements\Exception\InstallProductException;
use App\Core\Entitlements\Models\Product;
use App\SubscriptionBilling\SubscriptionBillingProduct;

class ProductInstaller
{
    public function __construct(
        private AccountsReceivableProduct $accountsReceivableProduct,
        private AccountsPayableProduct $accountsPayableProduct,
        private CashApplicationProduct $cashApplicationProduct,
        private CustomerPortalProduct $customerPortalProduct,
        private SubscriptionBillingProduct $subscriptionBillingProduct,
    ) {
    }

    /**
     * Installs a given product for a company.
     *
     * @throws InstallProductException
     */
    public function install(Product $product, Company $company): void
    {
        $company->features->enableProduct($product);

        foreach ($product->features as $productFeature) {
            $feature = $productFeature->feature;
            if ('accounts_payable' == $feature) {
                $this->accountsPayableProduct->install($company);
            } elseif ('accounts_receivable' == $feature) {
                $this->accountsReceivableProduct->install($company);
            } elseif ('cash_application' == $feature) {
                $this->cashApplicationProduct->install($company);
            } elseif ('billing_portal' == $feature) {
                $this->customerPortalProduct->install($company);
            } elseif ('subscription_billing' == $feature) {
                $this->subscriptionBillingProduct->install($company);
            }
        }
    }
}
