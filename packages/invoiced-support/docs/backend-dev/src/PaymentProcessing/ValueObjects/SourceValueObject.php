<?php

namespace App\PaymentProcessing\ValueObjects;

use App\AccountsReceivable\Models\Customer;
use App\PaymentProcessing\Models\MerchantAccount;

/**
 * Represents a payment source.
 */
abstract readonly class SourceValueObject
{
    public function __construct(
        public Customer $customer,
        public string $gateway,
        public ?string $gatewayId,
        public ?string $gatewayCustomer,
        public ?string $gatewaySetupIntent,
        public ?MerchantAccount $merchantAccount,
        public bool $chargeable,
        public ?string $receiptEmail,
    ) {
    }
}
