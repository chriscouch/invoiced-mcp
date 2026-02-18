<?php

namespace App\PaymentProcessing\Views\Payment;

use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentFlow;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\ValueObjects\PaymentForm;
use App\PaymentProcessing\ValueObjects\PaymentFormCapabilities;

/**
 * Renders a GoCardless payment form.
 */
class GoCardlessPaymentView extends AbstractPaymentView
{
    public function shouldBeShown(PaymentForm $form, PaymentMethod $paymentMethod, ?MerchantAccount $merchantAccount): bool
    {
        return true;
    }

    public function getPaymentFormCapabilities(): PaymentFormCapabilities
    {
        return new PaymentFormCapabilities(
            isSubmittable: false,
            supportsVaulting: false,
            supportsConvenienceFee: false,
            hasReceiptEmail: false
        );
    }

    protected function getTemplate(): string
    {
        return 'customerPortal/paymentMethods/paymentForms/gocardless.twig';
    }

    public function getViewParameters(PaymentForm $form, PaymentMethod $paymentMethod, ?MerchantAccount $merchantAccount, PaymentFlow $paymentFlow): array
    {
        $customer = $form->customer;

        return [
            'clientId' => $customer->client_id,
            'currency' => $form->currency,
            'description' => $form->getPaymentDescription($this->translator),
            'paymentMethod' => $paymentMethod,
            'subdomain' => $customer->tenant()->getSubdomainUsername(),
        ];
    }
}
