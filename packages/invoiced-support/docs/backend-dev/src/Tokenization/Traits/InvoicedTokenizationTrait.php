<?php

namespace App\Tokenization\Traits;

use App\AccountsReceivable\Models\Customer;
use App\Core\Multitenant\Exception\MultitenantException;
use App\Core\Orm\Exception\ModelNotFoundException;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\ValueObjects\BankAccountValueObject;
use App\PaymentProcessing\ValueObjects\CardValueObject;
use App\PaymentProcessing\ValueObjects\SourceValueObject;
use App\Tokenization\Enums\TokenizationApplicationType;
use App\Tokenization\Models\TokenizationApplication;

trait InvoicedTokenizationTrait
{
    /**
     * @throws ModelNotFoundException|MultitenantException
     */
    protected function vaultInvoicedSource(string $invoicedToken, Customer $customer, MerchantAccount $account): SourceValueObject
    {
        /** @var TokenizationApplication $token */
        $token = TokenizationApplication::where('identifier', $invoicedToken)->one();
        $tenant = $customer->tenant();

        return TokenizationApplicationType::CARD === $token->type
            ? new CardValueObject(
                customer: $customer,
                gateway: self::ID,
                gatewayId: $token->gateway_id,
                gatewayCustomer: $token->gateway_customer,
                merchantAccount: $account,
                chargeable: true,
                brand: $token->brand,
                funding: $token->funding,
                last4: $token->last4,
                expMonth: $token->exp_month,
                expYear: $token->exp_year,
                country: $token->country ?? $tenant->country,
            )
            : new BankAccountValueObject(
                customer: $customer,
                gateway: self::ID,
                gatewayId: $token->gateway_id,
                gatewayCustomer: $token->gateway_customer,
                merchantAccount: $account,
                chargeable: true,
                bankName: $token->bank_name ?? 'Unknown',
                routingNumber: $token->routing_number,
                last4: $token->last4,
                currency: $customer->currency ?? $tenant->currency,
                country: $token->country ?? $tenant->country ?? 'US',
                accountHolderName: $token->account_holder_name ?? $customer->name,
                accountHolderType: $token->account_holder_type,
                type: $token->account_type ?? null,
            );
    }
}
