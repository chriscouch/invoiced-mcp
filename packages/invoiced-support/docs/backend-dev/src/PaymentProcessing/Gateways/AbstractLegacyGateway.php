<?php

namespace App\PaymentProcessing\Gateways;

use App\AccountsReceivable\Models\Customer;
use App\Core\I18n\ValueObjects\Money;
use App\PaymentProcessing\Exceptions\ChargeException;
use App\PaymentProcessing\Exceptions\PaymentSourceException;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\ValueObjects\ChargeValueObject;
use App\PaymentProcessing\ValueObjects\SourceValueObject;


abstract class AbstractLegacyGateway extends AbstractGateway
{

    public function charge(Customer $customer, MerchantAccount $account, Money $amount, array $parameters, string $description, array $documents = []): ChargeValueObject
    {
        if ($account->tenant()->features->has('allow_invoiced_tokenization')) {
            return parent::charge($customer, $account, $amount, $parameters, $description, $documents);
        }

        throw new ChargeException("Merchant doesn't support Credit Card payments. Please use other payment method instead.");
    }

    public function vaultSource(Customer $customer, MerchantAccount $account, array $parameters): SourceValueObject
    {
        if ($account->tenant()->features->has('allow_invoiced_tokenization')) {
            return parent::vaultSource($customer, $account, $parameters);
        }

        throw new PaymentSourceException("Merchant doesn't support Credit Card payments. Please use other payment method instead.");
    }
}
