<?php

namespace App\PaymentProcessing\Views\PaymentInfo;

use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Company;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Models\TokenizationFlow;

class OPPAchPaymentInfoView extends AbstractPaymentInfoView
{
    public function shouldBeShown(Company $company, PaymentMethod $paymentMethod, ?MerchantAccount $merchantAccount, ?Customer $customer): bool
    {
        return true;
    }

    public function render(Company $company, PaymentMethod $paymentMethod, ?MerchantAccount $merchantAccount, TokenizationFlow $flow): string
    {
        return $this->twig->render(
            'customerPortal/paymentMethods/paymentInfoForms/achOPP.twig',
            $this->getViewParameters($paymentMethod, $flow->customer)
        );
    }

    public function getViewParameters(PaymentMethod $paymentMethod, ?Customer $customer): array
    {
        return [
            'achDebitTerms' => $paymentMethod->meta,
            'accountHolderType' => $customer?->type,
            'countries' => $this->getCountries(),
            'address' => [
                'first_name' => '',
                'last_name' => '',
                'name' => $customer?->name,
                'address1' => $customer?->address1,
                'address2' => $customer?->address2,
                'city' => $customer?->city,
                'state' => $customer?->state,
                'postal_code' => $customer?->postal_code,
                'country' => $customer?->country ?? $paymentMethod->tenant()->country,
            ],
        ];
    }
}
