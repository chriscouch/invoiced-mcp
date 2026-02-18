<?php

namespace App\PaymentProcessing\Interfaces;

use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentFlow;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\ValueObjects\PaymentForm;
use App\PaymentProcessing\ValueObjects\PaymentFormCapabilities;

interface PaymentViewInterface
{
    /**
     * Determines if the payment view should be shown in the given context.
     */
    public function shouldBeShown(PaymentForm $form, PaymentMethod $paymentMethod, ?MerchantAccount $merchantAccount): bool;

    /**
     * Gets the payment form capabilities for this payment view.
     */
    public function getPaymentFormCapabilities(): PaymentFormCapabilities;

    /**
     * Renders the payment view.
     */
    public function render(PaymentForm $form, PaymentMethod $paymentMethod, ?MerchantAccount $merchantAccount, PaymentFlow $paymentFlow): string;

    /**
     * Generates the view parameters to accompany the payment view.
     */
    public function getViewParameters(PaymentForm $form, PaymentMethod $paymentMethod, ?MerchantAccount $merchantAccount, PaymentFlow $paymentFlow): array;
}
