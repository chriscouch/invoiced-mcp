<?php

namespace App\CashApplication\TransactionBuilder;

use App\CashApplication\Models\Payment;
use App\CashApplication\Models\Transaction;
use App\PaymentProcessing\Models\PaymentMethod;

class ConvenienceFeeSplit extends AbstractSplit
{
    public function build(Payment $payment, array $split): array
    {
        $amount = $this->parsePaymentAmount($payment, $split);

        $transaction = new Transaction();
        $transaction->type = PaymentMethod::BALANCE == $payment->method ? Transaction::TYPE_CHARGE : Transaction::TYPE_PAYMENT;
        if ($customer = $payment->customer()) {
            $transaction->setCustomer($customer);
        }
        $transaction->date = $payment->date;
        $transaction->currency = $amount->currency;
        $transaction->amount = $amount->toDecimal();
        $transaction->method = $payment->method;
        $transaction->payment = $payment;
        $transaction->gateway_id = $payment->reference;

        // Charges
        if ($charge = $payment->charge) {
            $this->applyCharge($transaction, $charge);
        }

        $transaction->markConvenienceFee();

        return [$transaction];
    }
}
