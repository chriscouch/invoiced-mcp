<?php

namespace App\PaymentProcessing\Interfaces;

use App\PaymentProcessing\Exceptions\VerifyBankAccountException;
use App\PaymentProcessing\Models\BankAccount;
use App\PaymentProcessing\Models\MerchantAccount;

interface VerifyBankAccountInterface
{
    /**
     * Verifies a payment bank account in the payment gateway through micro-deposit verification.
     *
     * @param int $amount1 micro-deposit amount, in cents
     * @param int $amount2 micro-deposit amount, in cents
     *
     * @throws VerifyBankAccountException when the verification fails
     */
    public function verifyBankAccount(MerchantAccount $merchantAccount, BankAccount $bankAccount, int $amount1, int $amount2): void;
}
