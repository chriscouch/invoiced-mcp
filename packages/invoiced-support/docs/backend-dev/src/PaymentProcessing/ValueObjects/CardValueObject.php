<?php

namespace App\PaymentProcessing\ValueObjects;

use App\AccountsReceivable\Models\Customer;
use App\PaymentProcessing\Models\MerchantAccount;

/**
 * Represents a card payment source.
 */
final readonly class CardValueObject extends SourceValueObject
{
    public function __construct(
        Customer $customer,
        string $gateway = '',
        ?string $gatewayId = null,
        ?string $gatewayCustomer = null,
        ?string $gatewaySetupIntent = null,
        ?MerchantAccount $merchantAccount = null,
        bool $chargeable = false,
        ?string $receiptEmail = null,
        public string $brand = '',
        public string $funding = '',
        public string $last4 = '',
        public int $expMonth = 0,
        public int $expYear = 0,
        public ?string $country = null,
    ) {
        parent::__construct($customer, $gateway, $gatewayId, $gatewayCustomer, $gatewaySetupIntent, $merchantAccount, $chargeable, $receiptEmail);
    }
}
