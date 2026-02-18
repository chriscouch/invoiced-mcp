<?php

namespace App\PaymentProcessing\Views\Payment;

use App\PaymentProcessing\Gateways\OPPGateway;
use App\PaymentProcessing\Gateways\TestGateway;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentFlow;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\ValueObjects\PaymentForm;
use App\PaymentProcessing\ValueObjects\PaymentFormCapabilities;

/**
 * Renders a credit card payment form.
 */
class CardPaymentView extends AbstractPaymentView
{
    private bool $hasBillingAddress = true;

    public function shouldBeShown(PaymentForm $form, PaymentMethod $paymentMethod, ?MerchantAccount $merchantAccount): bool
    {
        return TestGateway::ID === $paymentMethod->gateway || $paymentMethod->tenant()->features->has('allow_invoiced_tokenization');
    }

    public function getPaymentFormCapabilities(): PaymentFormCapabilities
    {
        return new PaymentFormCapabilities(
            isSubmittable: true,
            supportsVaulting: true,
            supportsConvenienceFee: true,
            hasReceiptEmail: true
        );
    }

    protected function getTemplate(): string
    {
        return 'customerPortal/paymentMethods/paymentForms/card.twig';
    }

    public function getViewParameters(PaymentForm $form, PaymentMethod $paymentMethod, ?MerchantAccount $merchantAccount, PaymentFlow $paymentFlow): array
    {
        $customer = $form->customer;

        return [
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
            'currency' => $form->currency,
            'description' => $form->getPaymentDescription($this->translator),
            'hasBillingAddress' => $this->hasBillingAddress(),
            'isTestGateway' => TestGateway::ID == $merchantAccount?->gateway,
            'paymentMethod' => $paymentMethod,
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
