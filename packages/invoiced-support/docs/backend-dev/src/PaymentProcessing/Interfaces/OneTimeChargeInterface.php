<?php

namespace App\PaymentProcessing\Interfaces;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\Core\I18n\ValueObjects\Money;
use App\PaymentProcessing\Exceptions\ChargeException;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\ValueObjects\ChargeValueObject;

/**
 * Methods needed to perform a one-time charge.
 */
interface OneTimeChargeInterface
{
    /**
     * Creates a charge on the payment gateway with non-tokenized payment information.
     *
     * @param ReceivableDocument[] $documents
     *
     * @throws ChargeException
     */
    public function charge(Customer $customer, MerchantAccount $account, Money $amount, array $parameters, string $description, array $documents = []): ChargeValueObject;
}
