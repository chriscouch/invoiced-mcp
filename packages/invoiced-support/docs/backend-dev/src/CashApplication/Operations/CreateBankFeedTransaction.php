<?php

namespace App\CashApplication\Operations;

use App\CashApplication\Libs\CashApplicationMatchmaker;
use App\CashApplication\Libs\CashApplicationRulesEvaluator;
use App\CashApplication\Models\BankFeedTransaction;
use App\CashApplication\Models\Payment;
use App\Core\Orm\Exception\ModelException;
use App\PaymentProcessing\Models\PaymentMethod;

class CreateBankFeedTransaction
{
    private CashApplicationRulesEvaluator $evaluator;

    public function __construct(
        private CashApplicationMatchmaker $matchmaker,
    ) {
    }

    /**
     * @throws ModelException
     */
    public function create(BankFeedTransaction $bankFeedTransaction): BankFeedTransaction
    {
        // Find an existing transaction with the same transaction ID for this bank account
        $bankFeedTransaction2 = BankFeedTransaction::where('cash_application_bank_account_id', $bankFeedTransaction->cash_application_bank_account)
            ->where('transaction_id', $bankFeedTransaction->transaction_id)
            ->oneOrNull();
        if ($bankFeedTransaction2) {
            return $bankFeedTransaction2;
        }

        $bankFeedTransaction->saveOrFail();
        $this->loadPayment($bankFeedTransaction);

        return $bankFeedTransaction;
    }

    private function loadPayment(BankFeedTransaction $bankFeedTransaction): void
    {
        // Only negative transactions are considered as a possible payment.
        if ($bankFeedTransaction->amount >= 0) {
            return;
        }

        // This is used for backward compatibility with payments that do not have a bank feed transaction.
        // Eventually this check can be removed.
        $existingPayments = Payment::where('external_id', $bankFeedTransaction->transaction_id)
            ->where('source', Payment::SOURCE_BANK_FEED)
            ->count();
        if ($existingPayments > 0) {
            return;
        }

        $payment = new Payment();
        $payment->bank_feed_transaction = $bankFeedTransaction;
        $payment->plaid_bank_account = $bankFeedTransaction->cash_application_bank_account?->plaid_link;
        $payment->external_id = $bankFeedTransaction->transaction_id;
        $payment->date = $bankFeedTransaction->date->getTimestamp();
        $payment->amount = -$bankFeedTransaction->amount; // bank feed transaction amounts are negative when a payment was received
        $payment->source = Payment::SOURCE_BANK_FEED;
        $payment->reference = $bankFeedTransaction->payment_reference_number;

        // Detect ACH payment method
        if ($bankFeedTransaction->payment_ppd_id || 'ach' == strtolower((string) $bankFeedTransaction->payment_method)) {
            $payment->method = PaymentMethod::ACH;
        }

        // Apply cash application rules
        if (!$this->applyRules($bankFeedTransaction, $payment)) {
            return;
        }

        $payment->saveOrFail();

        // If the payment does not have a customer then we need to
        // start a cash match job.
        if ($this->matchmaker->shouldLookForMatches($payment)) {
            $this->matchmaker->enqueue($payment, false);
        }
    }

    private function applyRules(BankFeedTransaction $bankFeedTransaction, Payment $payment): bool
    {
        $company = $bankFeedTransaction->tenant();
        if (!$company->features->has('cash_match')) {
            return true;
        }

        if (!isset($this->evaluator)) {
            $this->evaluator = new CashApplicationRulesEvaluator();
        }

        $rules = $this->evaluator->getMatchedRules($bankFeedTransaction);

        foreach ($rules as $rule) {
            if ($rule->ignore) {
                return false;
            }

            if ($rule->customer) {
                $payment->customer = $rule->customer;
            }

            if ($rule->method) {
                $payment->method = $rule->method;
            }
        }

        return true;
    }
}
