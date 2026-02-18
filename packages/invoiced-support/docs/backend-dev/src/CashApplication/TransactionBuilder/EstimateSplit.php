<?php

namespace App\CashApplication\TransactionBuilder;

use App\AccountsReceivable\Models\Estimate;
use App\CashApplication\Exceptions\ApplyPaymentException;
use App\CashApplication\Models\Payment;
use App\CashApplication\Models\Transaction;

class EstimateSplit extends AbstractSplit
{
    public function build(Payment $payment, array $split): array
    {
        $amount = $this->parsePaymentAmount($payment, $split);

        if ($split['estimate'] instanceof Estimate) {
            $estimate = $split['estimate'];
        } else {
            $estimate = Estimate::find($split['estimate']);
            if (!$estimate) {
                throw new ApplyPaymentException('Could not find estimate: '.$split['estimate']);
            }
        }

        // perform validations
        $this->validateCurrencyMatches($estimate, $payment);
        $this->validateCustomerMatches($estimate, $payment);

        $transaction = new Transaction();
        $transaction->type = Transaction::TYPE_ADJUSTMENT;
        $transaction->setCustomer($estimate->customer());
        $transaction->setEstimate($estimate);
        $transaction->date = $payment->date;
        $transaction->currency = $amount->currency;
        $transaction->amount = $amount->negated()->toDecimal();
        $transaction->method = $payment->method;
        $transaction->payment = $payment;
        $transaction->gateway_id = $payment->reference;
        $transaction->notes = $payment->notes;

        // Charges
        if ($charge = $payment->charge) {
            $this->applyCharge($transaction, $charge);
        }

        return [$transaction];
    }
}
