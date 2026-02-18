<?php

namespace App\PaymentProcessing\ValueObjects;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\Companies\Models\Company;
use App\Core\I18n\ValueObjects\Money;
use App\PaymentProcessing\Models\PaymentMethod;

class PaymentInfoForm
{
    /**
     * @param PaymentMethod[] $methods
     * @param Invoice[]       $outstandingAutoPayInvoices
     */
    public function __construct(
        public readonly Company $company,
        public readonly ?Customer $customer = null,
        public readonly ?PaymentMethod $method = null,
        public readonly array $methods = [],
        public readonly ?string $selectedPaymentMethod = null,
        public readonly bool $openModalFlag = false,
        public readonly bool $forceAutoPay = false,
        public readonly bool $makeDefault = true,
        public readonly bool $allowAutoPayEnrollment = false,
        public readonly array $outstandingAutoPayInvoices = [],
        public readonly ?Money $outstandingAutoPayBalance = null,
    ) {
    }
}
