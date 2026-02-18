<?php

namespace App\CashApplication\TransactionBuilder;

use App\CashApplication\Models\Payment;
use App\CashApplication\Models\Transaction;
use App\PaymentProcessing\Models\PaymentMethod;

class CreditSplit extends AbstractSplit
{
    public function build(Payment $payment, array $split): array
    {
        $amount = $this->parsePaymentAmount($payment, $split);

        $transaction = new Transaction();
        $transaction->type = Transaction::TYPE_ADJUSTMENT;
        if ($customer = $payment->customer()) {
            $transaction->setCustomer($customer);
        }
        $transaction->date = $payment->date;
        $transaction->currency = $amount->currency;
        $transaction->amount = -$amount->toDecimal();
        $transaction->method = PaymentMethod::BALANCE;
        $transaction->payment = $payment;
        $transaction->notes = 'Overpayment';

        // Charges
        if ($charge = $payment->charge) {
            $this->applyCharge($transaction, $charge);
        }

        return [$transaction];
    }
}
