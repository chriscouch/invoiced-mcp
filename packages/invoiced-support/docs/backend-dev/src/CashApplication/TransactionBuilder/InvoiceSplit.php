<?php

namespace App\CashApplication\TransactionBuilder;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Exceptions\ApplyPaymentException;
use App\CashApplication\Models\Payment;
use App\CashApplication\Models\Transaction;
use App\Core\I18n\ValueObjects\Money;
use App\PaymentProcessing\Models\PaymentMethod;

class InvoiceSplit extends AbstractSplit
{
    public function build(Payment $payment, array $split): array
    {
        $amount = $this->parsePaymentAmount($payment, $split);

        // look up invoice
        if ($split['invoice'] instanceof Invoice) {
            $invoice = $split['invoice'];
        } else {
            $invoice = Invoice::find($split['invoice']);
            if (!$invoice) {
                throw new ApplyPaymentException('Could not find invoice: '.$split['invoice']);
            }
        }

        // perform validations
        $this->validateCurrencyMatches($invoice, $payment);
        $this->validateCustomerMatches($invoice, $payment);

        $transaction = new Transaction();
        $transaction->type = PaymentMethod::BALANCE == $payment->method ? Transaction::TYPE_CHARGE : Transaction::TYPE_PAYMENT;
        $transaction->setCustomer($invoice->customer());
        $transaction->setInvoice($invoice);
        $transaction->date = $payment->date;
        $transaction->currency = $amount->currency;
        $transaction->amount = $amount->toDecimal();
        $transaction->method = $payment->method;
        $transaction->payment = $payment;
        $transaction->gateway_id = $payment->reference;
        $transaction->notes = $payment->notes;

        // Charges
        if ($charge = $payment->charge) {
            $this->applyCharge($transaction, $charge);
        }

        $result = [$transaction];

        // Short Pay
        $remainingInvoiceBalance = Money::fromDecimal($invoice->currency, $invoice->total);
        $remainingInvoiceBalance = $remainingInvoiceBalance->subtract($amount);
        if (!empty($split['short_pay']) && $remainingInvoiceBalance->isPositive()) {
            /** @var Customer $customer */
            $customer = $payment->customer();
            $result[] = $this->shortPay($customer, $invoice, $payment, $remainingInvoiceBalance);
        }

        return $result;
    }

    private function shortPay(Customer $customer, Invoice $invoice, Payment $payment, Money $amount): CreditNote
    {
        $creditNote = new CreditNote();
        $creditNote->setCustomer($customer);
        $creditNote->setInvoice($invoice);
        $creditNote->date = $payment->date;
        $creditNote->currency = $amount->currency;
        $creditNote->calculate_taxes = false;
        $creditNote->items = [
            [
                'unit_cost' => $amount->toDecimal(),
                'type' => 'short_pay',
                'name' => 'Short Pay',
            ],
        ];

        return $creditNote;
    }
}
