<?php

namespace App\PaymentProcessing\Interfaces;

use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Company;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Models\TokenizationFlow;

interface PaymentInfoViewInterface
{
    /**
     * Determines if the payment info view should be shown in the given context.
     */
    public function shouldBeShown(Company $company, PaymentMethod $paymentMethod, ?MerchantAccount $merchantAccount, ?Customer $customer): bool;

    /**
     * Renders the payment info view.
     */
    public function render(Company $company, PaymentMethod $paymentMethod, ?MerchantAccount $merchantAccount, TokenizationFlow $flow): string;
}
