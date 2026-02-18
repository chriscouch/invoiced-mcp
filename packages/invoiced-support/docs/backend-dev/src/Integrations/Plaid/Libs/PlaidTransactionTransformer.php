<?php

namespace App\Integrations\Plaid\Libs;

use App\CashApplication\Models\BankFeedTransaction;
use App\CashApplication\Models\CashApplicationBankAccount;
use Carbon\CarbonImmutable;
use stdClass;

class PlaidTransactionTransformer
{
    /**
     * Transforms a Plaid transaction into a bank feed transaction model.
     */
    public function transform(CashApplicationBankAccount $bankAccount, stdClass $transaction): ?BankFeedTransaction
    {
        // Do not create a transaction until it is posted.
        if ($transaction->pending) {
            return null;
        }

        // These are required parameters
        if (!isset($transaction->transaction_id) || !isset($transaction->amount) || !isset($transaction->name) || !isset($transaction->date)) {
            return null;
        }

        $bankFeedTransaction = new BankFeedTransaction();
        $bankFeedTransaction->cash_application_bank_account = $bankAccount;
        $bankFeedTransaction->transaction_id = $transaction->transaction_id;
        $bankFeedTransaction->date = new CarbonImmutable($transaction->date);
        $bankFeedTransaction->amount = $transaction->amount;
        $bankFeedTransaction->description = $transaction->name;
        $bankFeedTransaction->check_number = $transaction->check_number ?? null;
        $bankFeedTransaction->merchant_name = $transaction->merchant_name ?? null;
        $bankFeedTransaction->payment_reference_number = $transaction->payment_meta->reference_number ?? null;
        $bankFeedTransaction->payment_ppd_id = $transaction->payment_meta->ppd_id ?? null;
        $bankFeedTransaction->payment_payee = $transaction->payment_meta->payee ?? null;
        $bankFeedTransaction->payment_by_order_of = $transaction->payment_meta->by_order_of ?? null;
        $bankFeedTransaction->payment_payer = $transaction->payment_meta->payer ?? null;
        $bankFeedTransaction->payment_method = $transaction->payment_meta->payment_method ?? null;
        $bankFeedTransaction->payment_processor = $transaction->payment_meta->payment_processor ?? null;
        $bankFeedTransaction->payment_reason = $transaction->payment_meta->reason ?? null;
        $bankFeedTransaction->payment_channel = $transaction->payment_channel ?? null;

        return $bankFeedTransaction;
    }
}
