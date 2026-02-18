<?php

namespace App\PaymentProcessing\ValueObjects;

use App\AccountsReceivable\Models\Customer;
use App\PaymentProcessing\Models\MerchantAccount;

/**
 * Represents a bank account payment source.
 */
final readonly class BankAccountValueObject extends SourceValueObject
{
    const TYPE_CHECKING = 'checking';
    const TYPE_SAVINGS = 'savings';

    const TYPE_COMPANY = 'company';

    const TYPE_INDIVIDUAL = 'individual';

    public function __construct(
        Customer $customer,
        string $gateway = '',
        ?string $gatewayId = null,
        ?string $gatewayCustomer = null,
        ?string $gatewaySetupIntent = null,
        ?MerchantAccount $merchantAccount = null,
        bool $chargeable = false,
        ?string $receiptEmail = null,
        public string $bankName = '',
        public ?string $routingNumber = null,
        public ?string $accountNumber = null,
        public string $last4 = '',
        public string $currency = '',
        public string $country = '',
        public ?string $accountHolderName = null,
        public ?string $accountHolderType = null,
        public ?string $type = null,
        public bool $verified = false,
    ) {
        parent::__construct($customer, $gateway, $gatewayId, $gatewayCustomer, $gatewaySetupIntent, $merchantAccount, $chargeable, $receiptEmail);
    }
}
