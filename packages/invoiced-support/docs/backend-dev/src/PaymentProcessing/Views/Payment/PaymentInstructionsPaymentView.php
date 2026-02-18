<?php

namespace App\PaymentProcessing\Views\Payment;

use App\Core\Utils\RandomString;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentFlow;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\ValueObjects\PaymentForm;
use App\PaymentProcessing\ValueObjects\PaymentFormCapabilities;

/**
 * Renders a payment form with payment instructions and a
 * promise-to-pay for payment methods like check and wire transfer.
 */
class PaymentInstructionsPaymentView extends AbstractPaymentView
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
        return 'customerPortal/paymentMethods/paymentForms/paymentInstructions.twig';
    }

    public function getViewParameters(PaymentForm $form, PaymentMethod $paymentMethod, ?MerchantAccount $merchantAccount, PaymentFlow $paymentFlow): array
    {
        return [
            'currency' => $form->currency,
            'description' => $form->getPaymentDescription($this->translator),
            'paymentMethod' => $paymentMethod,
            'paymentMethodId' => $paymentMethod->id,
            'paymentInstructions' => $paymentMethod->meta,
            'dateFieldId' => RandomString::generate(10, RandomString::CHAR_ALPHA),
        ];
    }
}
