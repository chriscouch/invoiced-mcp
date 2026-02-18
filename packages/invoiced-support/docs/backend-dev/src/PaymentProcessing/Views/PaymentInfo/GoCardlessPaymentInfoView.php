<?php

namespace App\PaymentProcessing\Views\PaymentInfo;

use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Company;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Models\TokenizationFlow;

/**
 * Renders a payment source form for GoCardless direct debit setup.
 */
class GoCardlessPaymentInfoView extends AbstractPaymentInfoView
{
    public function shouldBeShown(Company $company, PaymentMethod $paymentMethod, ?MerchantAccount $merchantAccount, ?Customer $customer): bool
    {
        return true;
    }

    public function render(Company $company, PaymentMethod $paymentMethod, ?MerchantAccount $merchantAccount, TokenizationFlow $flow): string
    {
        return $this->twig->render(
            'customerPortal/paymentMethods/paymentInfoForms/gocardless.twig',
            $this->getViewParameters($company, $flow->customer)
        );
    }

    public function getViewParameters(Company $company, ?Customer $customer): array
    {
        return [
            'clientId' => $customer?->client_id,
            'subdomain' => $company->getSubdomainUsername(),
        ];
    }
}
