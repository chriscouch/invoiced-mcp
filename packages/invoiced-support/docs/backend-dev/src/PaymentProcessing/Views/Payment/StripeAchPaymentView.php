<?php

namespace App\PaymentProcessing\Views\Payment;

use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentFlow;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\ValueObjects\PaymentForm;
use App\PaymentProcessing\ValueObjects\PaymentFormCapabilities;

/**
 * Renders a Stripe ACH payment form.
 */
class StripeAchPaymentView extends AbstractPaymentView
{
    public function shouldBeShown(PaymentForm $form, PaymentMethod $paymentMethod, ?MerchantAccount $merchantAccount): bool
    {
        $credentials = $merchantAccount?->credentials;

        return (bool) ($credentials?->key ?? '');
    }

    public function getPaymentFormCapabilities(): PaymentFormCapabilities
    {
        return new PaymentFormCapabilities(
            isSubmittable: false,
            supportsVaulting: true,
            supportsConvenienceFee: false,
            hasReceiptEmail: false
        );
    }

    protected function getTemplate(): string
    {
        return 'customerPortal/paymentMethods/paymentForms/achStripe.twig';
    }

    public function getViewParameters(PaymentForm $form, PaymentMethod $paymentMethod, ?MerchantAccount $merchantAccount, PaymentFlow $paymentFlow): array
    {
        $customer = $form->customer;

        return [
            'currency' => $form->currency,
            'description' => $form->getPaymentDescription($this->translator),
            'paymentMethod' => $paymentMethod,
            'clientId' => $customer->client_id,
            'methodId' => $paymentMethod->id,
            'subdomain' => $customer->tenant()->getSubdomainUsername(),
        ];
    }
}
