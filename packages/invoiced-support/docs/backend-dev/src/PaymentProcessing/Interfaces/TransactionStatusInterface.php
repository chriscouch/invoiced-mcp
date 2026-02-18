<?php

namespace App\PaymentProcessing\Interfaces;

use App\PaymentProcessing\Exceptions\TransactionStatusException;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\MerchantAccount;

/**
 * Interface for checking the status of previously pending transactions.
 */
interface TransactionStatusInterface
{
    /**
     * Gets the current status of a charge transaction.
     *
     * @throws TransactionStatusException when the transaction status cannot be determined
     *
     * @return array [status, message] Status is one of `succeeded`, `pending`, or `failed`. Message is the failure reason, if any.
     */
    public function getTransactionStatus(MerchantAccount $merchantAccount, Charge $charge): array;
}
