<?php

namespace App\PaymentProcessing\Libs;

use App\AccountsReceivable\Models\Customer;
use App\PaymentProcessing\Models\BankAccount;
use App\PaymentProcessing\Models\Card;
use App\PaymentProcessing\Models\PaymentSource;

class GetPaymentInfo
{
    const PAYMENT_SOURCE_LIMIT = 20;

    /**
     * Gets a list of payment sources for a customer.
     * NOTE: this will only return up to the payment source limit.
     * If using the includeHidden option then it will include the
     * first N payment sources for each type. Active sources will
     * be returned over inactive ones.
     *
     * @return PaymentSource[]
     */
    public function getAllActive(Customer $customer): array
    {
        $query = Card::where('customer_id', $customer->id())
            ->where('chargeable', true);

        $cards = $query->first(self::PAYMENT_SOURCE_LIMIT);

        $query = BankAccount::where('customer_id', $customer->id())
            ->where('chargeable', true);

        $bankAccounts = $query->first(self::PAYMENT_SOURCE_LIMIT);

        return array_merge($cards, $bankAccounts);
    }

    /**
     * Gets a list of payment sources for a customer.
     * NOTE: this will only return up to the payment source limit.
     * If using the includeHidden option then it will include the
     * first N payment sources for each type. Active sources will
     * be returned over inactive ones.
     *
     * @return PaymentSource[]
     */
    public function getAll(Customer $customer)
    {
        $cards = Card::where('customer_id', $customer->id())
            ->sort('chargeable DESC')
            ->first(self::PAYMENT_SOURCE_LIMIT);

        $bankAccounts = BankAccount::where('customer_id', $customer->id())
            ->sort('chargeable DESC')
            ->first(self::PAYMENT_SOURCE_LIMIT);

        return array_merge($cards, $bankAccounts);
    }
}
