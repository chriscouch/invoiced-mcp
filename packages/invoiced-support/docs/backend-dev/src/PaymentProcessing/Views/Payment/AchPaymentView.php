<?php

namespace App\PaymentProcessing\Views\Payment;

use App\PaymentProcessing\Gateways\TestGateway;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentFlow;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\ValueObjects\PaymentForm;
use App\PaymentProcessing\ValueObjects\PaymentFormCapabilities;

/**
 * Renders an ACH payment form.
 */
class AchPaymentView extends AbstractPaymentView
{
    public function shouldBeShown(PaymentForm $form, PaymentMethod $paymentMethod, ?MerchantAccount $merchantAccount): bool
    {
        return true;
    }

    public function getPaymentFormCapabilities(): PaymentFormCapabilities
    {
        return new PaymentFormCapabilities(
            isSubmittable: true,
            supportsVaulting: true,
            supportsConvenienceFee: false,
            hasReceiptEmail: true
        );
    }

    protected function getTemplate(): string
    {
        return 'customerPortal/paymentMethods/paymentForms/ach.twig';
    }

    public function getViewParameters(PaymentForm $form, PaymentMethod $paymentMethod, ?MerchantAccount $merchantAccount, PaymentFlow $paymentFlow): array
    {
        $customer = $form->customer;

        return [
            'accountHolderType' => $form->customer->type,
            'achDebitTerms' => $paymentMethod->meta,
            'isTestGateway' => TestGateway::ID == $merchantAccount?->gateway,
            'address' => [
                'name' => $customer->name,
                'address1' => $customer->address1,
                'address2' => $customer->address2,
                'city' => $customer->city,
                'state' => $customer->state,
                'postal_code' => $customer->postal_code,
                'country' => $customer->country ?? $form->company->country,
            ],
            'countries' => $this->getCountries(),
        ];
    }
}
