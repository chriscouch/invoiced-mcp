<?php

namespace App\PaymentProcessing\Views\PaymentInfo;

use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Company;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Models\TokenizationFlow;

class CardPaymentInfoView extends AbstractPaymentInfoView
{
    private bool $hasBillingAddress = true;

    public function shouldBeShown(Company $company, PaymentMethod $paymentMethod, ?MerchantAccount $merchantAccount, ?Customer $customer): bool
    {
        return true;
    }

    public function render(Company $company, PaymentMethod $paymentMethod, ?MerchantAccount $merchantAccount, TokenizationFlow $flow): string
    {
        return $this->twig->render(
            'customerPortal/paymentMethods/paymentInfoForms/card.twig',
            $this->getViewParameters($company, $flow->customer)
        );
    }

    public function getViewParameters(Company $company, ?Customer $customer): array
    {
        return [
            'hasBillingAddress' => $this->hasBillingAddress(),
            'countries' => $this->getCountries(),
            'address' => [
                'name' => $customer?->name,
                'address1' => $customer?->address1,
                'address2' => $customer?->address2,
                'city' => $customer?->city,
                'state' => $customer?->state,
                'postal_code' => $customer?->postal_code,
                'country' => $customer?->country ?? $company->country,
            ],
        ];
    }

    /**
     * Disables the form's billing address.
     */
    public function disableBillingAddress(): void
    {
        $this->hasBillingAddress = false;
    }

    /**
     * Checks if this form should collect a billing address.
     */
    public function hasBillingAddress(): bool
    {
        return $this->hasBillingAddress;
    }
}
