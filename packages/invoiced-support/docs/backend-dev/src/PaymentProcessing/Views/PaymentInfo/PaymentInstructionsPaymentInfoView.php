<?php

namespace App\PaymentProcessing\Views\PaymentInfo;

use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Company;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Models\TokenizationFlow;

/**
 * Renders payment instructions for payment methods
 * like check or wire transfer.
 */
class PaymentInstructionsPaymentInfoView extends AbstractPaymentInfoView
{
    public function shouldBeShown(Company $company, PaymentMethod $paymentMethod, ?MerchantAccount $merchantAccount, ?Customer $customer): bool
    {
        return true;
    }

    public function render(Company $company, PaymentMethod $paymentMethod, ?MerchantAccount $merchantAccount, TokenizationFlow $flow): string
    {
        return $this->twig->render(
            'customerPortal/paymentMethods/paymentInfoForms/paymentInstructions.twig',
            [
                'paymentInstructions' => $paymentMethod->meta,
            ]
        );
    }
}
